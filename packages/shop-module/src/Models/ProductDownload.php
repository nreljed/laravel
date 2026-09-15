<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDownload extends Model
{
    protected $fillable = [
        'product_id', 'name', 'file',
        'download_limit', 'download_expiry', 'sort_order',
    ];

    protected $casts = [
        'download_limit' => 'integer',
        'download_expiry' => 'integer',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isUnlimited(): bool
    {
        return $this->download_limit === -1;
    }

    public function isExpired(): bool
    {
        if (! $this->download_expiry || ! $this->created_at) {
            return false;
        }

        return $this->created_at->addDays($this->download_expiry)->isPast();
    }
}
