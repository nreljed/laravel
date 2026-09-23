<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Database\Eloquent\Collection;
use Nreljed\ShopModule\Discounts\DiscountManager;
use Nreljed\ShopModule\Discounts\Types\BuyXGetYDiscount;
use Nreljed\ShopModule\Discounts\Types\FixedDiscount;
use Nreljed\ShopModule\Discounts\Types\FixedProductDiscount;
use Nreljed\ShopModule\Discounts\Types\FreeShippingDiscount;
use Nreljed\ShopModule\Discounts\Types\PercentageDiscount;
use Nreljed\ShopModule\Discounts\Types\PercentageProductDiscount;
use Nreljed\ShopModule\Discounts\Types\TieredDiscount;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Services\CouponService;
use PHPUnit\Framework\TestCase;

/**
 * Contract + pure-math goldens for the discount engine.
 *
 * DB-free: every scenario here avoids the `products()` pivot query (BOGOF
 * fixtures pin `free_product_id`); product-scoped behaviour lives in
 * DiscountEngineGoldenTest.
 */
final class DiscountTypeContractTest extends TestCase
{
    public function test_default_manager_registers_all_seven_types(): void
    {
        $manager = DiscountManager::defaults();

        $this->assertInstanceOf(PercentageDiscount::class, $manager->driver('percentage'));
        $this->assertInstanceOf(FixedDiscount::class, $manager->driver('fixed'));
        $this->assertInstanceOf(FreeShippingDiscount::class, $manager->driver('free_shipping'));
        $this->assertInstanceOf(BuyXGetYDiscount::class, $manager->driver('buy_x_get_y'));
        $this->assertInstanceOf(FixedProductDiscount::class, $manager->driver('fixed_product'));
        $this->assertInstanceOf(PercentageProductDiscount::class, $manager->driver('percentage_product'));
        $this->assertInstanceOf(TieredDiscount::class, $manager->driver('tiered'));
        $this->assertNull($manager->driver('unknown'));
    }

    public function test_reduces_subtotal_mapping_matches_stacking_classification(): void
    {
        $manager = DiscountManager::defaults();

        foreach (['percentage', 'fixed', 'tiered'] as $type) {
            $this->assertTrue($manager->driver($type)->reducesSubtotal(), "$type should reduce the running subtotal.");
        }

        foreach (['free_shipping', 'buy_x_get_y', 'fixed_product', 'percentage_product'] as $type) {
            $this->assertFalse($manager->driver($type)->reducesSubtotal(), "$type should not reduce the running subtotal.");
        }
    }

    public function test_coupon_model_exposes_new_scope_fields(): void
    {
        $coupon = new Coupon;

        $this->assertTrue($coupon->isFillable('customer_group_id'));
        $this->assertTrue($coupon->isFillable('exclude_sale_items'));

        $coupon->forceFill(['customer_group_id' => 2, 'exclude_sale_items' => true]);

        $this->assertSame(2, $coupon->customer_group_id);
        $this->assertTrue($coupon->exclude_sale_items);
    }

    public function test_hero_subtitle_default_has_no_hardcoded_currency(): void
    {
        $config = require __DIR__.'/../config/shop.php';

        $this->assertStringNotContainsString('EUR', $config['storefront']['hero']['subtitle']);
    }

    public function test_percentage_discount_is_value_percent_of_subtotal(): void
    {
        $coupon = $this->coupon(['type' => 'percentage', 'value' => 10]);

        $this->assertSame(10.0, $coupon->calculateDiscount(100.0));
        $this->assertSame(5.5, $coupon->calculateDiscount(55.0));
    }

    public function test_percentage_discount_respects_max_discount(): void
    {
        $coupon = $this->coupon(['type' => 'percentage', 'value' => 25, 'max_discount' => 8]);

        $this->assertSame(8.0, $coupon->calculateDiscount(100.0));
    }

    public function test_fixed_discount_grants_value_with_cap(): void
    {
        $coupon = $this->coupon(['type' => 'fixed', 'value' => 5]);

        $this->assertSame(5.0, $coupon->calculateDiscount(100.0));

        $capped = $this->coupon(['type' => 'fixed', 'value' => 5, 'max_discount' => 3]);

        $this->assertSame(3.0, $capped->calculateDiscount(100.0));
    }

    public function test_fixed_discount_granted_above_subtotal_floor(): void
    {
        $coupon = $this->coupon(['type' => 'fixed', 'value' => 500]);

        $this->assertSame(500.0, $coupon->calculateDiscount(100.0));
    }

    public function test_free_shipping_grants_shipping_cost_or_zero(): void
    {
        $coupon = $this->coupon(['type' => 'free_shipping']);

        $this->assertSame(12.0, $coupon->calculateDiscount(100.0, 12.0));
        $this->assertSame(0.0, $coupon->calculateDiscount(100.0, null));
    }

    public function test_buy_x_get_y_grants_cheapest_free_units(): void
    {
        $coupon = $this->coupon(['type' => 'buy_x_get_y', 'buy_qty' => 2, 'free_qty' => 1, 'free_product_id' => 1]);

        $this->assertSame(
            20.0,
            $coupon->calculateDiscount(100.0, null, new Collection([$this->item(1, 6, 10)]))
        );

        $this->assertSame(
            30.0,
            $coupon->calculateDiscount(100.0, null, new Collection([$this->item(1, 9, 10)]))
        );
    }

    public function test_buy_x_get_y_draws_free_units_from_cheapest_eligible_items(): void
    {
        $coupon = $this->coupon(['type' => 'buy_x_get_y', 'buy_qty' => 1, 'free_qty' => 1, 'free_product_id' => 1]);

        $this->assertSame(
            10.0,
            $coupon->calculateDiscount(100.0, null, new Collection([
                $this->item(1, 1, 20),
                $this->item(1, 1, 10),
            ]))
        );
    }

    public function test_stack_free_shipping_does_not_reduce_running_subtotal(): void
    {
        $service = new CouponService;
        $free = $this->coupon(['type' => 'free_shipping']);
        $fixed = $this->coupon(['type' => 'fixed', 'value' => 5]);

        $stack = $service->calculateStackedDiscount(new Collection([$free, $fixed]), 100.0, 12.0);

        $this->assertSame(17.0, $stack['total_discount']);
        $this->assertSame([12.0, 5.0], array_column($stack['breakdown'], 'discount'));
    }

    public function test_stack_line_item_scoped_coupon_does_not_reduce_running_subtotal(): void
    {
        $service = new CouponService;
        $bogof = $this->coupon(['type' => 'buy_x_get_y', 'buy_qty' => 2, 'free_qty' => 1, 'free_product_id' => 1]);
        $fixed = $this->coupon(['type' => 'fixed', 'value' => 5]);

        $stack = $service->calculateStackedDiscount(
            new Collection([$bogof, $fixed]),
            100.0,
            null,
            new Collection([$this->item(1, 6, 10)])
        );

        $this->assertSame(25.0, $stack['total_discount']);
        $this->assertSame([20.0, 5.0], array_column($stack['breakdown'], 'discount'));
    }

    public function test_stack_subtotal_scoped_coupons_reduce_running_subtotal(): void
    {
        $service = new CouponService;
        $percent = $this->coupon(['type' => 'percentage', 'value' => 10]);
        $fixed = $this->coupon(['type' => 'fixed', 'value' => 5]);

        $stack = $service->calculateStackedDiscount(new Collection([$percent, $fixed]), 100.0);

        $this->assertSame(15.0, $stack['total_discount']);
        $this->assertSame([10.0, 5.0], array_column($stack['breakdown'], 'discount'));
    }

    public function test_coupon_is_valid_respects_customer_group(): void
    {
        $grouped = $this->coupon(['customer_group_id' => 2]);

        $this->assertTrue($grouped->isValid(100.0, 2));
        $this->assertFalse($grouped->isValid(100.0, 1));
        $this->assertFalse($grouped->isValid(100.0, null));

        $open = $this->coupon([]);

        $this->assertTrue($open->isValid(100.0, 2));
        $this->assertTrue($open->isValid(100.0, null));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function coupon(array $attributes): Coupon
    {
        return new Coupon(array_merge([
            'code' => 'T-' . strtoupper((string) random_int(1000, 9999)),
            'type' => 'percentage',
            'value' => 0,
            'is_active' => true,
        ], $attributes));
    }

    private function item(int $productId, int $quantity, float $unitPrice, ?Product $product = null): object
    {
        return new class($productId, $quantity, $unitPrice, $product)
        {
            public function __construct(
                public int $product_id,
                public int $quantity,
                public float $unit_price,
                public ?Product $product = null,
            ) {}
        };
    }
}