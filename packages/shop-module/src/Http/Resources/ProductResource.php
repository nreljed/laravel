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
            'stock_quantity' => (int) $this->stock_quantity,
            'track_stock' => (bool) $this->track_stock,
            'status' => $this->status,
            'type' => $this->type,
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->pluck('name')),
            'thumbnail_url' => $this->thumbnail_url,
            'average_rating' => (float) ($this->average_rating ?? 0),
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
