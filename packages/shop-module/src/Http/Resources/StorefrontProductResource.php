<?php

namespace Nreljed\ShopModule\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nreljed\ShopModule\Models\Product;

/**
 * The public storefront's view of a product.
 *
 * The storefront handed `Product` models straight to Inertia, which serialises
 * whatever `toArray()` produces. The model has no `$hidden`, so every column
 * went to the browser - including `cost_price` and `price_hash`, the figures a
 * merchant uses to decide whether to stock something at all, plus internal
 * bookkeeping (`hold_stock_minutes`, `incoming_stock`, `low_stock_threshold`,
 * `menu_order`, `created_by`, `tax_class`).
 *
 * This is an allowlist rather than a denylist on purpose: a new column on
 * `products` is private until someone adds it here, so the default answer to
 * "should this be public?" stays "no".
 *
 * The field list is the storefront's own contract - `ProductDetail` and
 * `ProductCard` in `components/storefront` declare exactly these, and nothing
 * there reads a cost field, so the trimmed payload is not a UI change.
 */
class StorefrontProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,

            'price' => (float) $this->price,
            'compare_price' => $this->compare_price !== null ? (float) $this->compare_price : null,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            // Appended by `PriceResolver::hydrate()` in the view layer, after
            // currency and customer-group conversion. Kept because the grid
            // and the detail page both display these rather than `price`.
            'active_price' => $this->active_price,
            'on_sale' => (bool) $this->on_sale,
            'is_in_stock' => (bool) $this->is_in_stock,
            'price_range' => $this->price_range,

            'sku' => $this->sku,
            'stock_quantity' => (int) $this->stock_quantity,
            'track_stock' => (bool) $this->track_stock,
            'backorders' => $this->backorders,
            'stock_status' => $this->stock_status,
            'incoming_stock' => $this->incoming_stock !== null ? (int) $this->incoming_stock : null,
            'low_stock_threshold' => (int) $this->low_stock_threshold,

            'type' => $this->type,
            'virtual' => (bool) $this->virtual,
            'downloadable' => (bool) $this->downloadable,
            'external_url' => $this->external_url,
            'button_text' => $this->button_text,
            'sold_individually' => (bool) $this->sold_individually,
            'min_quantity' => $this->min_quantity !== null ? (int) $this->min_quantity : null,
            'max_quantity' => $this->max_quantity !== null ? (int) $this->max_quantity : null,

            'images' => $this->images ?? [],
            'thumbnail_url' => $this->thumbnail_url,
            'average_rating' => (float) ($this->average_rating ?? 0),
            'reviews_count' => (int) ($this->reviews_count ?? 0),

            'categories' => $this->whenLoaded('categories', fn () => $this->categories
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])->values()),
            'reviews' => $this->whenLoaded('reviews', fn () => $this->reviews
                ->map(fn ($review) => [
                    'id' => $review->id,
                    'rating' => (float) $review->rating,
                    'title' => $review->title,
                    'content' => $review->content,
                    'user' => $review->user ? ['name' => $review->user->name] : null,
                    'created_at' => $review->created_at?->toISOString(),
                ])->values()),
            'downloads' => $this->whenLoaded('downloads', fn () => $this->downloads
                ->map(fn ($download) => [
                    'id' => $download->id,
                    'name' => $download->name,
                    'file' => $download->file,
                    'download_limit' => (int) $download->download_limit,
                    'download_expiry' => (int) $download->download_expiry,
                ])->values()),
            // `groupedItems` is a pivot relation, so the quantity a shopper is
            // actually buying lives on the pivot rather than the child.
            'groupedItems' => $this->whenLoaded('groupedItems', fn () => $this->groupedItems
                ->map(fn (Product $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'price' => (float) $child->price,
                    'thumbnail_url' => $child->thumbnail_url,
                    'pivot' => [
                        'quantity' => (int) ($child->pivot->quantity ?? 1),
                    ],
                ])->values()),
            'seo' => $this->when(isset($this->seo), fn () => [
                'meta_title' => $this->seo['meta_title'] ?? null,
                'meta_description' => $this->seo['meta_description'] ?? null,
                'og_image' => $this->seo['og_image'] ?? null,
            ]),

            // Deliberately absent: `cost_price`, `price_hash`, `status`,
            // `catalog_visibility`, `publish_date`, `unpublish_at`,
            // `min_variation_price`, `max_variation_price`, `sale_from`,
            // `sale_to`, `vendor`, `barcode`, `global_unique_id`,
            // `hold_stock_minutes`, `menu_order`, `is_featured`, `is_taxable`,
            // `tax_class`, `weight`, `length`, `width`, `height`,
            // `default_attributes`, `attributes`, `created_by`, `created_at`,
            // `updated_at`, `deleted_at`.
            //
            // `incoming_stock` and `low_stock_threshold` read like restock
            // internals but are public on purpose: the detail page shows
            // "(5 incoming)" beside the stock line and both storefront pages
            // raise a low-stock badge off the threshold. Do not "tidy" them
            // out - it breaks shopper-facing behaviour.
            //
            // The admin keeps the full model: `Http\Controllers\ProductsController`
            // renders its own props and is unaffected, which is why this is a
            // storefront resource and not a `$hidden` array on the model.
        ];
    }
}
