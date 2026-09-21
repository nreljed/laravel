<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Database\Factories\CouponFactory;

class Coupon extends Model
{
    use HasFactory;

    public const TYPES = [
        'percentage',
        'fixed',
        'free_shipping',
        'buy_x_get_y',
        'fixed_product',
        'percentage_product',
        'tiered',
    ];

    protected $fillable = [
        'code', 'type', 'value', 'min_order_amount', 'max_discount',
        'usage_limit', 'per_user_limit', 'used_count', 'is_active',
        'is_automatic', 'auto_discount_label',
        'buy_qty', 'free_qty', 'free_product_id', 'tiers',
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
        'buy_qty' => 'integer',
        'free_qty' => 'integer',
        'free_product_id' => 'integer',
        'tiers' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return CouponFactory::new();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function freeProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'free_product_id');
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

    /**
     * Calculate the discount this coupon grants.
     *
     * @param  Collection<int, object>|null  $lineItems  line items exposing
     *                                                   `product_id`, `unit_price` and `quantity`
     */
    public function calculateDiscount(float $subtotal, ?float $shippingCost = null, ?Collection $lineItems = null): float
    {
        if ($this->type === 'free_shipping') {
            return (float) ($shippingCost ?? 0);
        }

        if ($this->type === 'buy_x_get_y') {
            return $this->calculateBuyXGetY($lineItems);
        }

        if ($this->type === 'fixed_product' || $this->type === 'percentage_product') {
            return $this->calculateProductDiscount($subtotal, $lineItems);
        }

        if ($this->type === 'tiered') {
            return $this->calculateTieredDiscount($subtotal, $lineItems);
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

    protected function calculateBuyXGetY(?Collection $lineItems): float
    {
        if ($lineItems === null || $lineItems->isEmpty()) {
            return 0;
        }

        $buyQty = (int) ($this->buy_qty ?: 2);
        $freeQty = (int) ($this->free_qty ?: 1);

        $eligibleItems = $lineItems->filter(function ($item) {
            if ($this->free_product_id) {
                return (int) $item->product_id === (int) $this->free_product_id;
            }

            if ($this->products()->exists()) {
                return in_array((int) $item->product_id, $this->products()->pluck('products.id')->all(), true);
            }

            return true;
        });

        if ($eligibleItems->isEmpty()) {
            return 0;
        }

        $totalEligibleQty = (int) $eligibleItems->sum('quantity');
        $freeUnits = (int) floor($totalEligibleQty / ($buyQty + $freeQty)) * $freeQty;

        if ($freeUnits <= 0) {
            return 0;
        }

        // Free units are drawn from the cheapest eligible items first.
        $discount = 0;
        $remaining = $freeUnits;

        foreach ($eligibleItems->sortBy('unit_price') as $item) {
            if ($remaining <= 0) {
                break;
            }

            $qty = min((int) $item->quantity, $remaining);
            $discount += $qty * (float) $item->unit_price;
            $remaining -= $qty;
        }

        if ($this->max_discount) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return max(0, $discount);
    }

    protected function calculateProductDiscount(float $subtotal, ?Collection $lineItems): float
    {
        if ($lineItems === null || $lineItems->isEmpty()) {
            return 0;
        }

        $eligibleIds = $this->products()->pluck('products.id')->all();

        $eligibleSubtotal = 0;

        foreach ($lineItems as $item) {
            if (! in_array((int) $item->product_id, $eligibleIds, true)) {
                continue;
            }

            $itemSubtotal = (float) $item->unit_price * (int) $item->quantity;
            $eligibleSubtotal += $itemSubtotal;

            if ($this->type === 'percentage_product') {
                $discount = $itemSubtotal * ((float) $this->value / 100);
            } else {
                $discount = (float) $this->value * (int) $item->quantity;
            }

            $accumulatedDiscount ??= 0;
            $accumulatedDiscount += min($discount, $itemSubtotal);
        }

        $discount = $accumulatedDiscount ?? 0;

        $discount = min($discount, $eligibleSubtotal, max(0, $subtotal));

        if ($this->max_discount) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return max(0, $discount);
    }

    /**
     * Quantity-break tiers: `[{"min_qty": 5, "discount_percent": 10}, ...]`.
     *
     * The highest tier whose quantity threshold is met applies its percentage
     * to the eligible subtotal. If product-scoped, only those line items count.
     *
     * @param  Collection<int, object>|null  $lineItems  line items exposing
     *                                                   `product_id`, `unit_price` and `quantity`
     */
    protected function calculateTieredDiscount(float $subtotal, ?Collection $lineItems): float
    {
        $tiers = collect($this->tiers ?? [])
            ->filter(fn ($tier) => isset($tier['min_qty'], $tier['discount_percent']))
            ->sortByDesc(fn ($tier) => (int) $tier['min_qty'])
            ->values();

        if ($tiers->isEmpty() || $lineItems === null || $lineItems->isEmpty()) {
            return 0;
        }

        $eligibleIds = $this->products()->pluck('products.id')->all();

        $eligibleSubtotal = 0;
        $eligibleQty = 0;

        foreach ($lineItems as $item) {
            if (! empty($eligibleIds) && ! in_array((int) $item->product_id, $eligibleIds, true)) {
                continue;
            }

            $eligibleSubtotal += (float) $item->unit_price * (int) $item->quantity;
            $eligibleQty += (int) $item->quantity;
        }

        $tier = $tiers->first(fn ($tier) => (int) $tier['min_qty'] <= $eligibleQty);

        if ($tier === null || $eligibleSubtotal <= 0) {
            return 0;
        }

        // Unscoped tiered coupons are subtotal-scoped: they respect the running
        // subtotal so stacking with a percentage coupon reduces the same base
        // (matches `calculateStackedDiscount` classification). Product-scoped
        // tiered coupons apply to their own eligible line-item subtotal.
        $base = ! empty($eligibleIds) ? $eligibleSubtotal : min($eligibleSubtotal, max(0, $subtotal));

        $discount = $base * ((float) $tier['discount_percent'] / 100);

        $discount = min($discount, $eligibleSubtotal, max(0, $subtotal));

        if ($this->max_discount) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return max(0, $discount);
    }
}
