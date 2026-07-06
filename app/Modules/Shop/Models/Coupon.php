<?php

namespace App\Modules\Shop\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_order_amount', 'max_discount',
        'usage_limit', 'per_user_limit', 'used_count', 'is_active',
        'is_automatic', 'auto_discount_label',
        'starts_at', 'expires_at', 'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'is_automatic' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product')
            ->withTimestamps();
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function isValid(float $subtotal): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        if ($this->type !== 'free_shipping' && $this->min_order_amount && $subtotal < (float) $this->min_order_amount) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $subtotal, ?float $shippingCost = null): float
    {
        if ($this->type === 'free_shipping') {
            return (float) ($shippingCost ?? 0);
        }

        if ($this->type === 'percentage') {
            $discount = $subtotal * ((float) $this->value / 100);
        } else {
            $discount = (float) $this->value;
        }

        if ($this->max_discount) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return max(0, $discount);
    }
}
