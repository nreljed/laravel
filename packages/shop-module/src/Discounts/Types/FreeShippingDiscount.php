<?php

namespace Nreljed\ShopModule\Discounts\Types;

use Illuminate\Database\Eloquent\Collection;
use Nreljed\ShopModule\Discounts\DiscountType;
use Nreljed\ShopModule\Models\Coupon;

class FreeShippingDiscount implements DiscountType
{
    public function type(): string
    {
        return 'free_shipping';
    }

    public function calculate(Coupon $coupon, float $subtotal, ?float $shippingCost, ?Collection $lineItems): float
    {
        return (float) ($shippingCost ?? 0);
    }

    public function reducesSubtotal(): bool
    {
        return false;
    }
}