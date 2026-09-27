<?php

namespace Nreljed\ShopModule\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public storefront's view of a product variation.
 *
 * Served alongside `StorefrontProductResource` for variable products, and
 * previously as a raw `ProductVariation`. That model has no `$hidden` either, so
 * `barcode`, the dimension columns and the stock-policy appends
 * (`purchasable`, `backorders_allowed`, `backordered`) all reached the browser.
 * None of them are catalog data, and the variation picker in `ProductDetail`
 * reads only the fields listed here.
 */
class StorefrontVariationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'price' => $this->price !== null ? (float) $this->price : null,
            'compare_price' => $this->compare_price !== null ? (float) $this->compare_price : null,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            // Set by `PriceResolver::hydrate()`; the picker prices the
            // selection from this rather than the stored `price`.
            'active_price' => $this->active_price,
            'stock_quantity' => (int) $this->stock_quantity,
            'manage_stock' => $this->manage_stock,
            'backorders' => $this->backorders,
            'stock_status' => $this->stock_status,
            'image_url' => $this->image_url,
            'attributes' => $this->attributes,
            'is_active' => (bool) $this->is_active,

            // Deliberately absent: `barcode`, `weight`, `length`, `width`,
            // `height`, `visible`, `sort_order`, `description`, `images`,
            // `sale_from`, `sale_to`, `created_at`, `updated_at`, and the
            // `purchasable` / `backorders_allowed` / `backordered` /
            // `is_visible` appends, which are pricing decisions the client
            // should not be able to reach by editing its own payload.
        ];
    }
}
