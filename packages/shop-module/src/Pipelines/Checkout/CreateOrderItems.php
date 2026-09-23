<?php

namespace Nreljed\ShopModule\Pipelines\Checkout;

use Closure;
use Illuminate\Pipeline\Pipeline;
use Nreljed\ShopModule\Models\OrderItem;
use Nreljed\ShopModule\Pipelines\CartLineContext;
use Nreljed\ShopModule\Pipelines\CheckoutContext;

class CreateOrderItems
{
    public function handle(CheckoutContext $context, Closure $next)
    {
        foreach ($context->cart->items as $item) {
            $line = (new Pipeline(app()))
                ->send(new CartLineContext($item, $context->order, $context->quote['currency']))
                ->through(config('shop.pipelines.cart_lines'))
                ->thenReturn();

            OrderItem::create($line->attributes);
        }

        return $next($context);
    }
}