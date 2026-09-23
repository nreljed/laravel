<?php

namespace Nreljed\ShopModule\Pipelines\Checkout;

use Closure;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Pipelines\CheckoutContext;

class ResolveShippingMethod
{
    public function handle(CheckoutContext $context, Closure $next)
    {
        $context->shippingMethod = ShippingMethod::findOrFail($context->validated['shipping_method_id']);

        return $next($context);
    }
}