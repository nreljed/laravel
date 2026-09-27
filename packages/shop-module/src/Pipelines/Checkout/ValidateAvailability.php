<?php

namespace Nreljed\ShopModule\Pipelines\Checkout;

use Closure;
use Illuminate\Validation\ValidationException;
use Nreljed\ShopModule\Pipelines\CheckoutContext;

/**
 * Refuse to place an order containing a product the shop is no longer selling.
 *
 * The cart rejects an unavailable product when the line is added, but a cart is
 * allowed to outlive a product: a shop can unpublish or hide something between
 * the add and the checkout. Stock is already re-checked here for the same
 * reason, and availability belongs beside it - otherwise the order records a
 * sale of a product whose page 404s and whose price nobody could see.
 */
class ValidateAvailability
{
    public function handle(CheckoutContext $context, Closure $next)
    {
        foreach ($context->cart->items as $item) {
            $product = $item->product;

            if (! $product) {
                continue;
            }

            if (! $product->isPurchasable()) {
                throw ValidationException::withMessages([
                    'cart' => __(':name is no longer available. Please remove it from your cart to continue.', [
                        'name' => $item->product_name ?? $product->name,
                    ]),
                ]);
            }
        }

        return $next($context);
    }
}
