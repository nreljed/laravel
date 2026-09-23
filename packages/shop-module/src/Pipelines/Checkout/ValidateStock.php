<?php

namespace Nreljed\ShopModule\Pipelines\Checkout;

use Closure;
use Illuminate\Validation\ValidationException;
use Nreljed\ShopModule\Pipelines\CheckoutContext;
use Nreljed\ShopModule\Services\InventoryService;

class ValidateStock
{
    public function __construct(protected InventoryService $inventory) {}

    public function handle(CheckoutContext $context, Closure $next)
    {
        foreach ($context->cart->items as $item) {
            $product = $item->product;

            if (! $product) {
                continue;
            }

            if (! $this->inventory->validateStock($product, $item->quantity, $item->variation)) {
                throw ValidationException::withMessages([
                    'stock' => __(':name is out of stock or insufficient quantity.', ['name' => $item->product_name ?? $product->name]),
                ]);
            }
        }

        return $next($context);
    }
}
