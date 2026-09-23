<?php

namespace Nreljed\ShopModule\Pipelines\Cart;

use Closure;
use Nreljed\ShopModule\Pipelines\CartContext;
use Nreljed\ShopModule\Services\InventoryService;

class ValidatePurchaseQuantity
{
    public function __construct(protected InventoryService $inventory) {}

    public function handle(CartContext $context, Closure $next)
    {
        if ($context->product->sold_individually && $context->totalQuantity > 1) {
            $context->error = 'This product can only be purchased one at a time.';

            return $context;
        }

        if (! $this->inventory->validateStock($context->product, $context->totalQuantity, $context->variation)) {
            $context->error = 'Requested quantity exceeds available stock.';

            return $context;
        }

        return $next($context);
    }
}
