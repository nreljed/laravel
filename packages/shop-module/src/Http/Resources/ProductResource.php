<?php

namespace Nreljed\ShopModule\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'compare_price' => $this->compare_price ? (float) $this->compare_price : null,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'global_unique_id' => $this->global_unique_id,
            'mpn' => $this->mpn,
            'stock_quantity' => (int) $this->stock_quantity,
            'track_stock' => (bool) $this->track_stock,
            'type' => $this->type,
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->pluck('name')),
            'thumbnail_url' => $this->thumbnail_url,
            'average_rating' => (float) ($this->average_rating ?? 0),
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // `status` was here, and `catalog_visibility` is deliberately
            // absent. Both are editorial fields: this resource is only ever
            // returned for products that are already published and browsable,
            // so `status` could only ever read "published" while implying the
            // caller had a say in it. Publishing the workflow state of a
            // public feed invites clients to filter on it and reintroduces the
            // leak `ProductsController` had just closed.
        ];
    }
}
