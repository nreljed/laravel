<?php

namespace Nreljed\ShopModule\Pipelines\Cart;

use Closure;
use Nreljed\ShopModule\Pipelines\CartContext;

/**
 * Refuse a line item the shop is no longer selling.
 *
 * The public cart endpoint takes a bare `product_id`, so the storefront page
 * 404ing was never a lock on it: an unpublished or hidden product could be
 * added to a cart by id, sit there, and reach checkout. Runs first, ahead of
 * the variant and stock rules, because a withdrawn product is not a purchase
 * problem - there is nothing to buy.
 */
class ValidateProductAvailability
{
    public function handle(CartContext $context, Closure $next)
    {
        if (! $context->product->isPurchasable()) {
            $context->error = 'This product is no longer available.';

            return $context;
        }

        return $next($context);
    }
}
