<?php

namespace Nreljed\ShopModule\Discounts\Types;

use Illuminate\Database\Eloquent\Collection;
use Nreljed\ShopModule\Discounts\DiscountType;
use Nreljed\ShopModule\Models\Coupon;

abstract class LineItemType implements DiscountType
{
    public function reducesSubtotal(): bool
    {
        return false;
    }

    /**
     * @param  Collection<int, object>  $lineItems
     * @return Collection<int, object>
     */
    protected function excludeOnSale(Collection $lineItems, Coupon $coupon): Collection
    {
        if (! $coupon->exclude_sale_items) {
            return $lineItems;
        }

        return $lineItems->filter(fn ($item) => ! $this->isItemOnSale($item));
    }

    protected function isItemOnSale(object $item): bool
    {
        $product = $item->product ?? null;

        if ($product !== null) {
            return $product->on_sale === true;
        }

        $variation = $item->variation ?? null;

        return $variation !== null && $variation->on_sale === true;
    }
}