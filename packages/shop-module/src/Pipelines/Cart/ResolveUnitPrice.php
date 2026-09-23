<?php

namespace Nreljed\ShopModule\Pipelines\Cart;

use Closure;
use Nreljed\ShopModule\Pipelines\CartContext;

class ResolveUnitPrice
{
    public function handle(CartContext $context, Closure $next)
    {
        $context->unitPrice = ($context->variation?->effective_price) ?? $context->product->price;

        return $next($context);
    }
}
