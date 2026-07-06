<?php

namespace App\Modules\Shop\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = ['user_id', 'session_id', 'shipping_address', 'billing_address', 'coupon_code', 'discount', 'applied_coupons'];

    protected $casts = [
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'discount' => 'decimal:2',
        'applied_coupons' => 'array',
    ];

    protected $appends = ['subtotal', 'total'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function getSubtotalAttribute(): float
    {
        return $this->items->sum(fn ($item) => $item->unit_price * $item->quantity);
    }

    public function getTotalAttribute(): float
    {
        return max(0, $this->subtotal - $this->discount);
    }
}
