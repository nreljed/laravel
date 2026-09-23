<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Services\CouponService;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Golden matrix for the product-scoped + group-aware discount behaviours that
 * need a real (in-memory sqlite) schema.
 */
final class DiscountEngineGoldenTest extends TestCase
{
    private SQLiteConnection $db;

    private int $nextCouponId = 500;

    protected function setUp(): void
    {
        parent::setUp();

        $resolver = new ConnectionResolver;
        $this->db = new SQLiteConnection(new PDO('sqlite::memory:'));
        $resolver->addConnection('default', $this->db);
        $resolver->setDefaultConnection('default');
        Model::setConnectionResolver($resolver);

        $this->db->setSchemaGrammar(new SQLiteGrammar($this->db));
        $builder = new SchemaBuilder($this->db);

        $builder->create('coupons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->unique();
            $table->string('type');
            $table->decimal('value', 12, 2)->nullable();
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('per_user_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_automatic')->default(false);
            $table->string('auto_discount_label')->nullable();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->boolean('exclude_sale_items')->default(false);
            $table->integer('buy_qty')->nullable();
            $table->integer('free_qty')->nullable();
            $table->unsignedBigInteger('free_product_id')->nullable();
            $table->text('tiers')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $builder->create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->timestamp('sale_from')->nullable();
            $table->timestamp('sale_to')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        $builder->create('coupon_product', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('product_id');
        });

        $this->db->table('products')->insert([
            ['id' => 1, 'name' => 'Regular A', 'price' => 100, 'sale_price' => null, 'sale_from' => null, 'sale_to' => null],
            ['id' => 3, 'name' => 'Regular B', 'price' => 40, 'sale_price' => null, 'sale_from' => null, 'sale_to' => null],
        ]);

        $this->db->table('products')->insert([
            'id' => 2,
            'name' => 'On Sale',
            'price' => 50,
            'sale_price' => 30,
            'sale_from' => '2020-01-01 00:00:00',
            'sale_to' => '2030-01-01 00:00:00',
        ]);

        $this->registerValidatorFacade();
    }

    public function test_fixed_product_discounts_only_scoped_lines(): void
    {
        $coupon = $this->coupon(['type' => 'fixed_product', 'value' => 10], [1, 3]);

        $discount = $coupon->calculateDiscount(1000.0, null, new Collection([
            $this->item(1, 2, 100),
            $this->item(2, 1, 30, $this->saleProduct()),
            $this->item(3, 1, 40),
        ]));

        $this->assertSame(30.0, $discount);
    }

    public function test_percentage_product_discounts_percent_of_scoped_subtotal(): void
    {
        $coupon = $this->coupon(['type' => 'percentage_product', 'value' => 10], [1, 3]);

        $discount = $coupon->calculateDiscount(1000.0, null, new Collection([
            $this->item(1, 2, 100),
            $this->item(2, 1, 30, $this->saleProduct()),
            $this->item(3, 1, 40),
        ]));

        $this->assertSame(24.0, $discount);
    }

    public function test_product_discount_is_capped_by_running_subtotal(): void
    {
        $coupon = $this->coupon(['type' => 'fixed_product', 'value' => 30], [1]);

        $discount = $coupon->calculateDiscount(20.0, null, new Collection([
            $this->item(1, 1, 50),
        ]));

        $this->assertSame(20.0, $discount);
    }

    public function test_exclude_sale_items_filters_sale_lines_from_product_scope(): void
    {
        $items = new Collection([
            $this->item(1, 1, 100),
            $this->item(2, 1, 30, $this->saleProduct()),
        ]);

        $inclusive = $this->coupon(['type' => 'fixed_product', 'value' => 5], [1, 2]);
        $this->assertSame(10.0, $inclusive->calculateDiscount(1000.0, null, $items));

        $exclusive = $this->coupon(['type' => 'fixed_product', 'value' => 5, 'exclude_sale_items' => true], [1, 2]);
        $this->assertSame(5.0, $exclusive->calculateDiscount(1000.0, null, $items));
    }

    public function test_buy_x_get_y_product_scoping_and_sale_exclusion(): void
    {
        $items = new Collection([
            $this->item(1, 2, 100),
            $this->item(2, 2, 30, $this->saleProduct()),
        ]);

        $inclusive = $this->coupon(['type' => 'buy_x_get_y', 'buy_qty' => 2, 'free_qty' => 1], [1, 2]);
        $this->assertSame(30.0, $inclusive->calculateDiscount(1000.0, null, $items));

        $exclusive = $this->coupon(
            ['type' => 'buy_x_get_y', 'buy_qty' => 2, 'free_qty' => 1, 'exclude_sale_items' => true],
            [1, 2],
        );
        $this->assertSame(0.0, $exclusive->calculateDiscount(1000.0, null, $items));
    }

    public function test_tiered_scoped_uses_eligible_subtotal_and_respects_sale_exclusion(): void
    {
        $items = new Collection([
            $this->item(1, 2, 100),
            $this->item(2, 1, 30, $this->saleProduct()),
        ]);

        $coupon = $this->coupon(['type' => 'tiered', 'tiers' => [['min_qty' => 3, 'discount_percent' => 10]]], [1, 2]);
        $this->assertSame(23.0, $coupon->calculateDiscount(1000.0, null, $items));

        $exclusive = $this->coupon(
            ['type' => 'tiered', 'tiers' => [['min_qty' => 3, 'discount_percent' => 10]], 'exclude_sale_items' => true],
            [1, 2],
        );
        $this->assertSame(0.0, $exclusive->calculateDiscount(1000.0, null, $items));
    }

    public function test_tiered_unscoped_is_bounded_by_running_subtotal(): void
    {
        $coupon = $this->coupon(['type' => 'tiered', 'tiers' => [['min_qty' => 3, 'discount_percent' => 10]]]);
        $items = new Collection([$this->item(1, 4, 100)]);

        $this->assertSame(40.0, $coupon->calculateDiscount(1000.0, null, $items));
        $this->assertSame(5.0, $coupon->calculateDiscount(50.0, null, $items));
    }

    public function test_tiered_reduces_running_subtotal_in_stack(): void
    {
        $tiered = $this->coupon(['type' => 'tiered', 'tiers' => [['min_qty' => 3, 'discount_percent' => 10]]]);
        $fixed = $this->coupon(['type' => 'fixed', 'value' => 5]);

        $stack = (new CouponService)->calculateStackedDiscount(
            new Collection([$tiered, $fixed]),
            1000.0,
            null,
            new Collection([$this->item(1, 4, 100)]),
        );

        $this->assertSame([40.0, 5.0], array_column($stack['breakdown'], 'discount'));
        $this->assertSame(45.0, $stack['total_discount']);
    }

    public function test_validate_and_apply_rejects_customer_group_mismatch(): void
    {
        $this->seedCoupon(['id' => 100, 'code' => 'GRP2', 'customer_group_id' => 2]);
        $service = new CouponService;

        $this->assertSame(100, $service->validateAndApply('GRP2', 100.0, null, 2)->id);

        foreach ([1, null] as $group) {
            try {
                $service->validateAndApply('GRP2', 100.0, null, $group);
                $this->fail('Expected a customer-group validation failure.');
            } catch (ValidationException $e) {
                $this->assertSame(['coupon' => ['This coupon is only available to your customer group.']], $e->errors());
            }
        }
    }

    public function test_validate_and_apply_ignores_group_when_coupon_has_none(): void
    {
        $this->seedCoupon(['id' => 101, 'code' => 'OPEN']);

        $service = new CouponService;

        $this->assertSame(101, $service->validateAndApply('OPEN', 100.0, null, 2)->id);
        $this->assertSame(101, $service->validateAndApply('OPEN', 100.0, null, null)->id);
    }

    public function test_auto_discounts_respect_customer_group_filter(): void
    {
        $this->seedCoupon(['id' => 1, 'code' => 'AUTO_NONE', 'is_automatic' => true, 'value' => 5]);
        $this->seedCoupon(['id' => 2, 'code' => 'AUTO_G2', 'is_automatic' => true, 'value' => 5, 'customer_group_id' => 2]);
        $this->seedCoupon(['id' => 3, 'code' => 'AUTO_G3', 'is_automatic' => true, 'value' => 5, 'customer_group_id' => 3]);

        $service = new CouponService;

        $this->assertSame(['AUTO_NONE'], $service->getAutoDiscounts(100.0, null)->pluck('code')->all());
        $this->assertSame(['AUTO_NONE', 'AUTO_G2'], $service->getAutoDiscounts(100.0, 2)->pluck('code')->all());
        $this->assertSame(['AUTO_NONE', 'AUTO_G3'], $service->getAutoDiscounts(100.0, 3)->pluck('code')->all());
    }

    protected function registerValidatorFacade(): void
    {
        $container = new Container;
        $container->instance('translator', new Translator(new ArrayLoader, 'en'));
        $container->singleton('validator', fn ($c) => new ValidationFactory($c['translator']));
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $productIds
     */
    private function coupon(array $attributes, array $productIds = []): Coupon
    {
        $attributes['id'] = $attributes['id'] ?? $this->nextCouponId++;

        $coupon = new Coupon(array_merge([
            'code' => 'T-' . strtoupper((string) random_int(1000, 9999)),
            'type' => 'percentage',
            'value' => 0,
            'is_active' => true,
        ], $attributes));

        $coupon->id = $attributes['id'];

        foreach ($productIds as $productId) {
            $this->db->table('coupon_product')->insert([
                'coupon_id' => $coupon->id,
                'product_id' => $productId,
            ]);
        }

        return $coupon;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedCoupon(array $attributes): void
    {
        $this->db->table('coupons')->insert(array_merge([
            'code' => '',
            'type' => 'fixed',
            'value' => 5,
            'min_order_amount' => null,
            'max_discount' => null,
            'usage_limit' => null,
            'per_user_limit' => null,
            'used_count' => 0,
            'is_active' => true,
            'is_automatic' => false,
            'auto_discount_label' => null,
            'customer_group_id' => null,
            'exclude_sale_items' => false,
            'buy_qty' => null,
            'free_qty' => null,
            'free_product_id' => null,
            'tiers' => null,
            'starts_at' => null,
            'expires_at' => null,
            'created_by' => null,
            'created_at' => '2026-09-23 10:00:00',
            'updated_at' => '2026-09-23 10:00:00',
        ], $attributes));
    }

    private function saleProduct(): Product
    {
        return new Product([
            'name' => 'On Sale (model)',
            'price' => 50,
            'sale_price' => 30,
            'sale_from' => '2020-01-01 00:00:00',
            'sale_to' => '2030-01-01 00:00:00',
        ]);
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