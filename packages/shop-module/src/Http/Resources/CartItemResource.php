<?php

namespace Nreljed\ShopModule\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nreljed\ShopModule\Models\CartItem;

/**
 * One line of a cart, as a shopper is allowed to see it.
 *
 * `CartController` returned the cart with `items.product` loaded straight off
 * the relation, and both cart routes are public:
 *
 *  - `GET /store/cart` renders the cart page,
 *  - `GET /store/cart/json` is the JSON feed the header badge polls.
 *
 * Inertia and `response()->json()` both serialise whatever `toArray()`
 * produces, and `Product` declares no `$hidden`, so a single line item
 * published the merchant's `cost_price` and `price_hash`, plus `status`,
 * `catalog_visibility`, `publish_date`, `unpublish_at`, `hold_stock_minutes`,
 * `created_by` and the rest of the row. The storefront trim
 * (`StorefrontProductResource`) was written to stop exactly that, but the cart
 * was never routed through it - the two ends of the same customer journey had
 * two different privacy postures.
 *
 * The product is delegated rather than re-listed, so the cart cannot drift from
 * the catalog allowlist when a field is added or removed in one place.
 *
 * `available` is the answer to "can this still be bought?". A product can be
 * withdrawn, sold out or unpublished between being added and being paid for,
 * and the line has to stay in the cart: making it vanish reads as the shop
 * losing the shopper's basket, and it hides the reason the checkout is
 * refusing. The line is kept, flagged, and the existing cart and checkout
 * pipelines already refuse to sell it - this only tells the UI which lines
 * those are.
 */
class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var CartItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'variant_label' => $item->variant_label,

            // Null when the product row is gone entirely (a soft delete, or a
            // cart that outlived a deleted catalogue entry). The cart page
            // renders around it rather than dereferencing it.
            'product' => $item->product
                ? StorefrontProductResource::make($item->product)->resolve($request)
                : null,

            'available' => $item->product?->isPurchasable() ?? false,

            'variation' => $item->variation ? [
                'id' => $item->variation->id,
                'image_url' => $item->variation->image_url,
            ] : null,
        ];
    }
}
