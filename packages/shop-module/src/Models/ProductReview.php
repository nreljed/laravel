<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nreljed\Platform\Models\User;

class ProductReview extends Model
{
    protected $fillable = [
        'product_id', 'user_id', 'order_id',
        'rating', 'title', 'content',
        'is_approved', 'approved_at', 'approved_by',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (ProductReview $review) {
            $review->product->updateRatingCache();
        });

        static::deleted(function (ProductReview $review) {
            $review->product->updateRatingCache();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }
}
