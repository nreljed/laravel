<?php

namespace Nreljed\ShopModule\Pipelines;

use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\CartItem;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;

class CartContext
{
    public function __construct(
        public Cart $cart,
        public Product $product,
        public ?ProductVariation $variation,
        public int $quantity,
        public ?CartItem $existingItem,
        public ?string $variantLabel,
        public int $totalQuantity,
        public ?float $unitPrice = null,
        public ?string $error = null,
    ) {}
}