<?php

namespace Nreljed\ShopModule\Discounts\Types;

use Nreljed\ShopModule\Models\Coupon;

class FixedProductDiscount extends ProductScopedDiscount
{
    public function type(): string
    {
        return 'fixed_product';
    }

    protected function perItemDiscount(float $itemSubtotal, int $quantity, Coupon $coupon): float
    {
        return (float) $coupon->value * $quantity;
    }
}
