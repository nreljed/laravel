<?php

namespace Nreljed\ShopModule\Discounts\Types;

use Illuminate\Database\Eloquent\Collection;
use Nreljed\ShopModule\Models\Coupon;

abstract class ProductScopedDiscount extends LineItemType
{
    abstract protected function perItemDiscount(float $itemSubtotal, int $quantity, Coupon $coupon): float;

    public function calculate(Coupon $coupon, float $subtotal, ?float $shippingCost, ?Collection $lineItems): float
    {
        if ($lineItems === null || $lineItems->isEmpty()) {
            return 0;
        }

        $lineItems = $this->excludeOnSale($lineItems, $coupon);

        $eligibleIds = $coupon->products()->pluck('products.id')->all();

        $eligibleSubtotal = 0;

        foreach ($lineItems as $item) {
            if (! in_array((int) $item->product_id, $eligibleIds, true)) {
                continue;
            }

            $itemSubtotal = (float) $item->unit_price * (int) $item->quantity;
            $eligibleSubtotal += $itemSubtotal;

            $discount = $this->perItemDiscount($itemSubtotal, (int) $item->quantity, $coupon);

            $accumulatedDiscount ??= 0;
            $accumulatedDiscount += min($discount, $itemSubtotal);
        }

        $discount = $accumulatedDiscount ?? 0;

        $discount = min($discount, $eligibleSubtotal, max(0, $subtotal));

        if ($coupon->max_discount) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return max(0, $discount);
    }
}