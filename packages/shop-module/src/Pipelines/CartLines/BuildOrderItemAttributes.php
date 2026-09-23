<?php

namespace Nreljed\ShopModule\Pipelines\CartLines;

use Closure;
use Nreljed\ShopModule\Pipelines\CartLineContext;

class BuildOrderItemAttributes
{
    public function handle(CartLineContext $context, Closure $next)
    {
        $itemSubtotal = $context->item->unit_price * $context->item->quantity;

        $context->attributes = [
            'order_id' => $context->order->id,
            'product_id' => $context->item->product_id,
            'product_variation_id' => $context->item->product_variation_id,
            'product_name' => $context->item->product->name,
            'product_sku' => $context->item->variation?->sku ?? $context->item->product->sku,
            'unit_price' => $context->item->unit_price,
            'quantity' => $context->item->quantity,
            'currency' => $context->currency,
            'subtotal' => $itemSubtotal,
            'total' => $itemSubtotal,
            'variant_label' => $context->item->variant_label,
            'image_url' => $context->item->variation?->image_url ?? $context->item->product->thumbnail_url,
        ];

        return $next($context);
    }
}
