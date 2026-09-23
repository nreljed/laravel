<?php

namespace Nreljed\ShopModule\Discounts\Types;

use Illuminate\Database\Eloquent\Collection;
use Nreljed\ShopModule\Models\Coupon;

class TieredDiscount extends LineItemType
{
    public function type(): string
    {
        return 'tiered';
    }

    public function reducesSubtotal(): bool
    {
        return true;
    }

    public function calculate(Coupon $coupon, float $subtotal, ?float $shippingCost, ?Collection $lineItems): float
    {
        $tiers = collect($coupon->tiers ?? [])
            ->filter(fn ($tier) => isset($tier['min_qty'], $tier['discount_percent']))
            ->sortByDesc(fn ($tier) => (int) $tier['min_qty'])
            ->values();

        if ($tiers->isEmpty() || $lineItems === null || $lineItems->isEmpty()) {
            return 0;
        }

        $lineItems = $this->excludeOnSale($lineItems, $coupon);

        $eligibleIds = $coupon->products()->pluck('products.id')->all();

        $eligibleSubtotal = 0;
        $eligibleQty = 0;

        foreach ($lineItems as $item) {
            if (! empty($eligibleIds) && ! in_array((int) $item->product_id, $eligibleIds, true)) {
                continue;
            }

            $eligibleSubtotal += (float) $item->unit_price * (int) $item->quantity;
            $eligibleQty += (int) $item->quantity;
        }

        $tier = $tiers->first(fn ($tier) => (int) $tier['min_qty'] <= $eligibleQty);

        if ($tier === null || $eligibleSubtotal <= 0) {
            return 0;
        }

        $base = ! empty($eligibleIds) ? $eligibleSubtotal : min($eligibleSubtotal, max(0, $subtotal));

        $discount = $base * ((float) $tier['discount_percent'] / 100);

        $discount = min($discount, $eligibleSubtotal, max(0, $subtotal));

        if ($coupon->max_discount) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return max(0, $discount);
    }
}