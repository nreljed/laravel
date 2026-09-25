<?php

namespace Nreljed\ShopModule\Pipelines\Cart;

use Closure;
use Nreljed\ShopModule\Pipelines\CartContext;
use Nreljed\ShopModule\Services\CustomerPricingContext;
use Nreljed\ShopModule\Services\PriceResolver;

/**
 * Prices a cart line through the ledger so the cart charges the same amount the
 * storefront displayed (PR 10C).
 *
 * This previously read the raw `product.price` / `variation.effective_price`
 * columns, which meant a customer-group override was shown on the product page
 * and then ignored at checkout.
 */
class ResolveUnitPrice
{
    public function __construct(
        protected PriceResolver $priceResolver,
        protected CustomerPricingContext $pricingContext,
    ) {}

    public function handle(CartContext $context, Closure $next)
    {
        $groupId = $this->pricingContext->groupId();
        $priceable = $context->variation ?: $context->product;

        $context->unitPrice = $this->priceResolver->activePrice($priceable, null, $groupId);

        return $next($context);
    }
}
