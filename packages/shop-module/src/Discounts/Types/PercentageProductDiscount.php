<?php

namespace Nreljed\ShopModule\Discounts\Types;

use Nreljed\ShopModule\Models\Coupon;

class PercentageProductDiscount extends ProductScopedDiscount
{
    public function type(): string
    {
        return 'percentage_product';
    }

    protected function perItemDiscount(float $itemSubtotal, int $quantity, Coupon $coupon): float
    {
        return $itemSubtotal * ((float) $coupon->value / 100);
    }
}