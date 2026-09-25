<?php

namespace Nreljed\ShopModule\Pipelines\Cart;

use Closure;
use Nreljed\ShopModule\Pipelines\CartContext;
use Nreljed\ShopModule\Services\CurrencyService;

class PersistLine
{
    public function __construct(protected CurrencyService $currency) {}

    public function handle(CartContext $context, Closure $next)
    {
        if ($context->existingItem) {
            // Re-price the existing line as well as re-quantifying it: a
            // customer-group price (or a price edit) must take effect on lines
            // that were added before it existed.
            $context->existingItem->update([
                'quantity' => $context->totalQuantity,
                'unit_price' => $context->unitPrice,
            ]);
        } else {
            $context->cart->items()->create([
                'product_id' => $context->product->id,
                'product_variation_id' => $context->variation?->id,
                'quantity' => $context->quantity,
                'unit_price' => $context->unitPrice,
                'currency' => $this->currency->defaultCode(),
                'variant_label' => $context->variantLabel,
            ]);
        }

        return $next($context);
    }
}