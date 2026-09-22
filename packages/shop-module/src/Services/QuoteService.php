<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\ShippingMethod;

/**
 * Single source of truth for checkout money math.
 *
 * Both the display path (`CheckoutController::index`) and the persist path
 * (`CheckoutService::processCheckout`) build a quote through this service so
 * the totals shown on the page always match what is stored on the order.
 *
 * Money decisions enforced here:
 *   - coupon stacking via `CouponService::calculateStackedDiscount`
 *   - free-above-shipping zeroing, then free_shipping-coupon zeroing
 *   - **tax is computed on the post-discount subtotal** (Decision A)
 *   - a currency + exchange-rate snapshot at quote time
 */
class QuoteService
{
    public function __construct(
        protected CouponService $couponService,
        protected TaxService $taxService,
        protected CurrencyService $currencyService,
    ) {}

    /**
     * @return array{
     *     subtotal: float,
     *     discount_total: float,
     *     discount_breakdown: list<array{coupon_id: int, code: string, type: string, discount: float}>,
     *     shipping_total: float,
     *     free_above_applied: bool,
     *     free_shipping_applied: bool,
     *     tax: array{tax_total: float, breakdown: list<array<string, mixed>>, base: float, prices_include_tax: bool},
     *     total: float,
     *     currency: string,
     *     exchange_rate: float,
     *     coupons: Collection<int, Coupon>,
     * }
     */
    public function quote(
        Cart $cart,
        ?ShippingMethod $shippingMethod = null,
        ?array $billingAddress = null,
        ?array $shippingAddress = null,
        ?int $userId = null,
    ): array {
        $subtotal = (float) $cart->subtotal;

        $shippingTotal = $shippingMethod ? (float) $shippingMethod->price : 0.0;
        $freeAboveApplied = $shippingMethod !== null
            && $shippingMethod->free_above !== null
            && $subtotal >= (float) $shippingMethod->free_above;

        if ($freeAboveApplied) {
            $shippingTotal = 0.0;
        }

        $validCoupons = $this->resolveCoupons($cart, $subtotal, $userId, $shippingTotal);

        $discountTotal = 0.0;
        $discountBreakdown = [];
        $freeShippingApplied = false;

        if ($validCoupons->isNotEmpty()) {
            $stack = $this->couponService->calculateStackedDiscount(
                $validCoupons, $subtotal, $shippingTotal, $cart->items
            );

            $discountTotal = (float) $stack['total_discount'];
            $discountBreakdown = $stack['breakdown'];

            foreach ($stack['breakdown'] as $b) {
                if ($b['type'] === 'free_shipping') {
                    $freeShippingApplied = true;
                    $shippingTotal = 0.0;
                }
            }
        }

        $currency = $this->currencyService->getDefaultCurrency();
        $currencyCode = $currency?->code ?? $this->currencyService->defaultCode();
        $exchangeRate = $currency ? (float) $currency->exchange_rate : 1.0;

        // Decision A: tax is computed on the POST-discount subtotal.
        $taxBase = max(0.0, $subtotal - $discountTotal);

        $tax = $this->taxService->calculate(
            $taxBase,
            $billingAddress,
            $shippingAddress,
            $shippingTotal,
        );

        $total = (float) max(0.0, $subtotal - $discountTotal + $shippingTotal + $tax['tax_total']);

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'discount_breakdown' => $discountBreakdown,
            'shipping_total' => $shippingTotal,
            'free_above_applied' => $freeAboveApplied,
            'free_shipping_applied' => $freeShippingApplied,
            'tax' => $tax,
            'total' => $total,
            'currency' => $currencyCode,
            'exchange_rate' => $exchangeRate,
            'coupons' => $validCoupons,
        ];
    }

    /**
     * Resolve the effective coupon set the same way everywhere: applied codes
     * are re-validated at quote time (expired/consumed ones are dropped rather
     * than shown and later silently removed), then active auto-discounts are
     * merged in.
     *
     * @return Collection<int, Coupon>
     */
    protected function resolveCoupons(Cart $cart, float $subtotal, ?int $userId, float $shippingTotal): Collection
    {
        $validCoupons = new Collection;

        $appliedCoupons = $cart->applied_coupons ?? [];

        if (empty($appliedCoupons) && $cart->coupon_code) {
            $appliedCoupons = [['code' => $cart->coupon_code]];
        }

        foreach ($appliedCoupons as $applied) {
            $code = $applied['code'] ?? null;
            if (! $code) {
                continue;
            }

            try {
                $coupon = $this->couponService->validateAndApply($code, $subtotal, $userId);
                $validCoupons->push($coupon);
            } catch (ValidationException) {
                // Coupon no longer valid at quote time.
            }
        }

        foreach ($this->couponService->getAutoDiscounts($subtotal) as $auto) {
            if ($validCoupons->contains('id', $auto->id)) {
                continue;
            }
            $validCoupons->push($auto);
        }

        return $validCoupons;
    }
}