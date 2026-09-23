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
            $context->existingItem->update(['quantity' => $context->totalQuantity]);
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
