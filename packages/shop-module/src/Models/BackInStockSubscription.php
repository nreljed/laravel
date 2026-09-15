<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackInStockSubscription extends Model
{
    protected $fillable = [
        'email', 'product_id', 'product_variation_id', 'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    public function scopeUnnotified($query)
    {
        return $query->whereNull('notified_at');
    }

    public function scopeForProduct($query, int $productId, ?int $variationId = null)
    {
        return $query->where('product_id', $productId)
            ->where('product_variation_id', $variationId);
    }
}
