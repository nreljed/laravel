<?php

namespace Nreljed\ShopModule\Pipelines;

use Nreljed\ShopModule\Models\CartItem;
use Nreljed\ShopModule\Models\Order;

class CartLineContext
{
    public function __construct(
        public CartItem $item,
        public Order $order,
        public string $currency,
        public array $attributes = [],
    ) {}
}