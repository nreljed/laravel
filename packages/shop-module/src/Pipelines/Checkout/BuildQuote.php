<?php

namespace Nreljed\ShopModule\Pipelines\Checkout;

use Closure;
use Nreljed\ShopModule\Pipelines\CheckoutContext;
use Nreljed\ShopModule\Services\QuoteService;

class BuildQuote
{
    public function __construct(protected QuoteService $quotes) {}

    public function handle(CheckoutContext $context, Closure $next)
    {
        $context->quote = $this->quotes->quote(
            $context->cart,
            $context->shippingMethod,
            $context->validated['billing_address'],
            $context->validated['shipping_address'] ?? null,
            $context->user?->id,
        );

        return $next($context);
    }
}