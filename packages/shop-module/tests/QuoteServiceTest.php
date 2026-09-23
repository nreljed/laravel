<?php

namespace Nreljed\ShopModule\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\CartItem;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Currency;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Services\CouponService;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\QuoteService;
use Nreljed\ShopModule\Services\ShopSettingsService;
use Nreljed\ShopModule\Services\TaxService;
use PHPUnit\Framework\TestCase;

/**
 * Golden matrix for QuoteService — the single source of truth for checkout
 * math.
 *
 * DB-free: DB-backed collaborators (CouponService validation, TaxService
 * rate lookup, CurrencyService default) are faked; the real
 * `CouponService::calculateStackedDiscount` alg runs so stacking behaviour is
 * covered end to end.
 */
final class QuoteServiceTest extends TestCase
{
    public function test_applied_coupon_only_coupon_stack(): void
    {
        $cart = $this->makeCart([['coupon_id' => 1, 'code' => 'HELLO10']]);
        $coupon = $this->makeCoupon(1, 'HELLO10', 'percentage', 10);

        $service = $this->makeService(
            validateMap: ['HELLO10' => $coupon],
            taxRate: 20,
        );

        $quote = $service->quote(
            cart: $cart,
            billingAddress: ['country' => 'GB'],
        );

        $this->assertSame(100.0, $quote['subtotal']);
        $this->assertSame(10.0, $quote['discount_total']);
        $this->assertSame(0.0, $quote['shipping_total']);
        // Decision A: tax is computed on the POST-discount subtotal.
        $this->assertSame(90.0, $quote['tax']['base']);
        $this->assertSame(18.0, $quote['tax']['tax_total']);
        $this->assertSame(108.0, $quote['total']);
        $this->assertSame(1, $quote['coupons']->count());
        $this->assertSame('HELLO10', $quote['coupons']->first()->code);
    }

    public function test_stacked_coupons_reduce_running_subtotal(): void
    {
        $cart = $this->makeCart([['coupon_id' => 1, 'code' => 'PERCENT'], ['coupon_id' => 2, 'code' => 'FIXED5']]);
        $percent = $this->makeCoupon(1, 'PERCENT', 'percentage', 10);
        $fixed = $this->makeCoupon(2, 'FIXED5', 'fixed', 5);

        $service = $this->makeService(
            validateMap: ['PERCENT' => $percent, 'FIXED5' => $fixed],
            taxRate: 0,
        );

        $quote = $service->quote(cart: $cart);

        // 10% of 100 = 10 (running 90), then fixed 5.
        $this->assertSame(15.0, $quote['discount_total']);
        $this->assertCount(2, $quote['discount_breakdown']);
        $this->assertSame(10.0, $quote['discount_breakdown'][0]['discount']);
        $this->assertSame(5.0, $quote['discount_breakdown'][1]['discount']);
        $this->assertSame(85.0, $quote['total']);
    }

    public function test_free_above_removes_shipping(): void
    {
        $cart = $this->makeCart([]);
        $method = new ShippingMethod(['price' => 10, 'free_above' => 100]);

        $quote = $this->makeService(taxRate: 20)->quote(
            cart: $cart,
            shippingMethod: $method,
            billingAddress: ['country' => 'DE'],
        );

        $this->assertSame(0.0, $quote['shipping_total']);
        $this->assertTrue($quote['free_above_applied']);
        $this->assertFalse($quote['free_shipping_applied']);
        $this->assertSame(20.0, $quote['tax']['tax_total']);
    }

    public function test_shipping_is_charged_below_free_above_threshold(): void
    {
        $cart = $this->makeCart([]);
        $method = new ShippingMethod(['price' => 10, 'free_above' => 200]);

        $quote = $this->makeService(taxRate: 20)->quote(
            cart: $cart,
            shippingMethod: $method,
            billingAddress: ['country' => 'DE'],
        );

        $this->assertSame(10.0, $quote['shipping_total']);
        $this->assertFalse($quote['free_above_applied']);
        $this->assertSame(130.0, $quote['total']);
    }

    public function test_free_shipping_coupon_zeroes_shipping(): void
    {
        $cart = $this->makeCart([['coupon_id' => 3, 'code' => 'FREESHIP']]);
        $coupon = $this->makeCoupon(3, 'FREESHIP', 'free_shipping', 0);
        $method = new ShippingMethod(['price' => 12, 'free_above' => null]);

        $quote = $this->makeService(
            validateMap: ['FREESHIP' => $coupon],
            taxRate: 0,
        )->quote(
            cart: $cart,
            shippingMethod: $method,
            billingAddress: ['country' => 'DE'],
        );

        $this->assertTrue($quote['free_shipping_applied']);
        $this->assertSame(0.0, $quote['shipping_total']);
        $this->assertSame(88.0, $quote['total']);
    }

    public function test_auto_discounts_mergen_into_stack(): void
    {
        $cart = $this->makeCart([]);
        $auto = $this->makeCoupon(5, 'AUTO5', 'fixed', 5);

        $quote = $this->makeService(
            autoDiscounts: new Collection([$auto]),
            taxRate: 0,
        )->quote(cart: $cart);

        $this->assertSame(5.0, $quote['discount_total']);
        $this->assertCount(1, $quote['coupons']);
    }

    public function test_invalid_applied_coupon_is_skipped_without_throwing(): void
    {
        $cart = $this->makeCart([['coupon_id' => 1, 'code' => 'EXPIRED'], ['coupon_id' => 2, 'code' => 'GOOD']]);
        $good = $this->makeCoupon(2, 'GOOD', 'fixed', 3);

        $service = $this->makeService(
            validateMap: ['GOOD' => $good],
            throwOn: ['EXPIRED'],
            taxRate: 0,
        );

        $quote = $service->quote(cart: $cart);

        $this->assertSame(3.0, $quote['discount_total']);
        $this->assertCount(1, $quote['coupons']);
        $this->assertSame('GOOD', $quote['coupons']->first()->code);
    }

    public function test_legacy_coupon_code_fallback_is_used_when_no_applied_coupons(): void
    {
        $cart = $this->makeCart([], couponCode: 'LEGACY');
        $coupon = $this->makeCoupon(9, 'LEGACY', 'fixed', 4);

        $service = $this->makeService(
            validateMap: ['LEGACY' => $coupon],
            taxRate: 0,
        );

        $quote = $service->quote(cart: $cart);

        $this->assertSame(4.0, $quote['discount_total']);
    }

    public function test_coupon_code_fallback_is_ignored_when_applied_coupons_exist(): void
    {
        $cart = $this->makeCart([['coupon_id' => 1, 'code' => 'REAL']], couponCode: 'LEGACY');
        $real = $this->makeCoupon(1, 'REAL', 'fixed', 7);

        $service = $this->makeService(
            validateMap: ['REAL' => $real],
            throwOn: ['LEGACY'],
            taxRate: 0,
        );

        $quote = $service->quote(cart: $cart);

        $this->assertSame(7.0, $quote['discount_total']);
        $this->assertSame('REAL', $quote['coupons']->first()->code);
    }

    public function test_currency_snapshot_uses_default_currency(): void
    {
        $cart = $this->makeCart([]);
        $currency = new Currency(['code' => 'USD', 'exchange_rate' => 1.08, 'decimal_places' => 2]);

        $service = new QuoteService(
            $this->fakeCouponService([], [], new Collection),
            $this->fakeTaxService(0),
            $this->fakeCurrencyService($currency),
        );

        $quote = $service->quote(cart: $cart);

        $this->assertSame('USD', $quote['currency']);
        $this->assertSame(1.08, $quote['exchange_rate']);
    }

    public function test_total_never_goes_below_zero(): void
    {
        $cart = $this->makeCart([['coupon_id' => 1, 'code' => 'HUGE']]);
        $coupon = $this->makeCoupon(1, 'HUGE', 'fixed', 500);

        $quote = $this->makeService(
            validateMap: ['HUGE' => $coupon],
            taxRate: 0,
        )->quote(cart: $cart);

        $this->assertSame(0.0, $quote['total']);
    }

    /*
    |--------------------------------------------------------------------------
    | Fixtures
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<int, array{coupon_id: int, code: string}>  $appliedCoupons
     */
    private function makeCart(array $appliedCoupons, ?string $couponCode = null): Cart
    {
        $cart = new Cart([
            'applied_coupons' => $appliedCoupons,
            'coupon_code' => $couponCode,
            'discount' => 0,
        ]);

        $cart->setRelation('items', new Collection([
            new CartItem(['product_id' => 1, 'quantity' => 2, 'unit_price' => 50]),
        ]));

        return $cart;
    }

    private function makeCoupon(int $id, string $code, string $type, float $value): Coupon
    {
        return new Coupon([
            'id' => $id,
            'code' => $code,
            'type' => $type,
            'value' => $value,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, Coupon>  $validateMap
     * @param  list<string>  $throwOn
     */
    private function makeService(
        array $validateMap = [],
        array $throwOn = [],
        Collection $autoDiscounts = new Collection,
        float $taxRate = 0,
    ): QuoteService {
        $couponService = $this->fakeCouponService($validateMap, $throwOn, $autoDiscounts);
        $currencyService = $this->fakeCurrencyService(
            new Currency(['code' => 'EUR', 'exchange_rate' => 1.0, 'decimal_places' => 2])
        );

        return new QuoteService(
            $couponService,
            $this->fakeTaxService($taxRate),
            $currencyService,
        );
    }

    /**
     * @param  array<string, Coupon>  $validateMap
     * @param  list<string>  $throwOn
     */
    private function fakeCouponService(array $validateMap, array $throwOn, Collection $autoDiscounts): CouponService
    {
        return new class($validateMap, $throwOn, $autoDiscounts) extends CouponService
        {
            /** @param  array<string, Coupon>  $validateMap */
            public function __construct(
                private array $validateMap,
                private array $throwOn,
                private Collection $autoDiscounts,
            ) {
                parent::__construct();
            }

            public function validateAndApply(string $code, float $subtotal, ?int $userId = null, ?int $customerGroupId = null): Coupon
            {
                if (in_array($code, $this->throwOn, true)) {
                    throw $this->invalid();
                }

                if (! isset($this->validateMap[$code])) {
                    throw $this->invalid();
                }

                return $this->validateMap[$code];
            }

            public function getAutoDiscounts(float $subtotal, ?int $customerGroupId = null): Collection
            {
                return $this->autoDiscounts;
            }

            private function invalid(): ValidationException
            {
                return new FakeValidationException;
            }
        };
    }

    private function fakeTaxService(float $rate): TaxService
    {
        return new class($rate) extends TaxService
        {
            public function __construct(private float $rate) {}

            public function calculate(
                float $subtotal,
                ?array $billingAddress = null,
                ?array $shippingAddress = null,
                float $shippingTotal = 0,
                bool $isTaxable = true,
            ): array {
                return [
                    'tax_total' => round($subtotal * $this->rate / 100, 2),
                    'breakdown' => [['name' => 'VAT', 'rate' => $this->rate, 'type' => 'percentage', 'amount' => round($subtotal * $this->rate / 100, 2)]],
                    'base' => round($subtotal, 2),
                    'prices_include_tax' => false,
                ];
            }
        };
    }

    private function fakeCurrencyService(Currency $currency): CurrencyService
    {
        return new class($currency) extends CurrencyService
        {
            public function __construct(private Currency $defaultCurrency)
            {
                parent::__construct(new ShopSettingsService);
            }

            public function getDefaultCurrency(): ?Currency
            {
                return $this->defaultCurrency;
            }
        };
    }
}

/**
 * Container-free stand-in for ValidationException: the framework factory
 * (withMessages) boots the Validator facade, which would crash a DB-free test.
 */
final class FakeValidationException extends ValidationException
{
    public function __construct()
    {
        // Intentionally skip the parent constructor (requires a container Validator).
    }
}