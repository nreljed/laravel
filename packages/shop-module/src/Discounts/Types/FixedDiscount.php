<?php

namespace Nreljed\ShopModule\Discounts\Types;

use Illuminate\Database\Eloquent\Collection;
use Nreljed\ShopModule\Discounts\DiscountType;
use Nreljed\ShopModule\Models\Coupon;

class FixedDiscount implements DiscountType
{
    public function type(): string
    {
        return 'fixed';
    }

    public function calculate(Coupon $coupon, float $subtotal, ?float $shippingCost, ?Collection $lineItems): float
    {
        $discount = (float) $coupon->value;

        if ($coupon->max_discount) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return max(0, $discount);
    }

    public function reducesSubtotal(): bool
    {
        return true;
    }
}