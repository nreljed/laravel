<?php

namespace Nreljed\ShopModule\Pipelines\Checkout;

use Closure;
use Nreljed\ShopModule\Pipelines\CheckoutContext;

class ClearCart
{
    public function handle(CheckoutContext $context, Closure $next)
    {
        $context->cart->items()->delete();
        $context->cart->update([
            'coupon_code' => null,
            'discount' => 0,
            'applied_coupons' => null,
            'currency' => $context->quote['currency'],
            'converted_to_order_id' => $context->order->id,
        ]);

        return $next($context);
    }
}