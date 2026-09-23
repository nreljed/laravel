<?php

namespace Nreljed\ShopModule\Discounts;

use Illuminate\Database\Eloquent\Collection;
use Nreljed\ShopModule\Models\Coupon;

interface DiscountType
{
    public function type(): string;

    /**
     * @param  Collection<int, object>|null  $lineItems
     */
    public function calculate(Coupon $coupon, float $subtotal, ?float $shippingCost, ?Collection $lineItems): float;

    public function reducesSubtotal(): bool;
}
