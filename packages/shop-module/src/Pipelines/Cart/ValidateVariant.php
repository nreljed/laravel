<?php

namespace Nreljed\ShopModule\Pipelines\Cart;

use Closure;
use Nreljed\ShopModule\Pipelines\CartContext;

class ValidateVariant
{
    public function handle(CartContext $context, Closure $next)
    {
        $variation = $context->variation;
        $product = $context->product;

        if ($variation) {
            if ($variation->product_id !== $product->id) {
                $context->error = 'Variant does not belong to this product.';

                return $context;
            }

            if (! $variation->isVisible()) {
                $context->error = 'This variant is not available for purchase.';

                return $context;
            }

            if ($variation->usesParentStock()) {
                if (! $product->isInStock()) {
                    $context->error = 'This variant is out of stock.';

                    return $context;
                }
            } elseif ($variation->managesOwnStock() && $variation->stock_quantity <= 0 && $variation->backorders === 'no') {
                $context->error = 'This variant is out of stock.';

                return $context;
            }
        } elseif (! $product->isInStock()) {
            $context->error = 'Product is out of stock.';

            return $context;
        }

        return $next($context);
    }
}