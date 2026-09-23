<?php

namespace Nreljed\ShopModule\Discounts\Types;

use Illuminate\Database\Eloquent\Collection;
use Nreljed\ShopModule\Models\Coupon;

class BuyXGetYDiscount extends LineItemType
{
    public function type(): string
    {
        return 'buy_x_get_y';
    }

    public function calculate(Coupon $coupon, float $subtotal, ?float $shippingCost, ?Collection $lineItems): float
    {
        if ($lineItems === null || $lineItems->isEmpty()) {
            return 0;
        }

        $buyQty = (int) ($coupon->buy_qty ?: 2);
        $freeQty = (int) ($coupon->free_qty ?: 1);

        $lineItems = $this->excludeOnSale($lineItems, $coupon);

        $eligibleItems = $lineItems->filter(function ($item) use ($coupon) {
            if ($coupon->free_product_id) {
                return (int) $item->product_id === (int) $coupon->free_product_id;
            }

            if ($coupon->products()->exists()) {
                return in_array((int) $item->product_id, $coupon->products()->pluck('products.id')->all(), true);
            }

            return true;
        });

        if ($eligibleItems->isEmpty()) {
            return 0;
        }

        $totalEligibleQty = (int) $eligibleItems->sum('quantity');
        $freeUnits = (int) floor($totalEligibleQty / ($buyQty + $freeQty)) * $freeQty;

        if ($freeUnits <= 0) {
            return 0;
        }

        $discount = 0;
        $remaining = $freeUnits;

        foreach ($eligibleItems->sortBy('unit_price') as $item) {
            if ($remaining <= 0) {
                break;
            }

            $qty = min((int) $item->quantity, $remaining);
            $discount += $qty * (float) $item->unit_price;
            $remaining -= $qty;
        }

        if ($coupon->max_discount) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return max(0, $discount);
    }
}
