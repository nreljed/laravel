<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-currency price ledger row (PR 2 / Decision 1, extended in PR 10C).
 *
 * One row per (product OR variation, customer group, currency, min_quantity).
 * Product-level rows keep `product_variation_id` null; variation-level rows
 * also set the parent `product_id` so both scopes are reachable.
 *
 * A null `customer_group_id` is the public price; a set one is an override for
 * members of that customer group (see {@see PriceResolver}).
 */
class ProductPrice extends Model
{
    protected $fillable = [
        'product_id', 'product_variation_id', 'customer_group_id', 'currency',
        'price', 'compare_price', 'sale_price', 'min_quantity',
    ];

    protected $casts = [
        'price' => 'float',
        'compare_price' => 'float',
        'sale_price' => 'float',
        'min_quantity' => 'integer',
        'customer_group_id' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function scopeCurrency($query, string $code)
    {
        return $query->where('currency', strtoupper($code));
    }
}
