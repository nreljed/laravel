<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Database\Factories\CouponFactory;
use Nreljed\ShopModule\Discounts\DiscountManager;
use Nreljed\ShopModule\Discounts\DiscountType;

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
        'customer_group_id', 'exclude_sale_items',
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
        'customer_group_id' => 'integer',
        'exclude_sale_items' => 'boolean',
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

    public function isValid(float $subtotal, ?int $customerGroupId = null): bool
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

        if ($this->customer_group_id !== null && (int) $this->customer_group_id !== (int) $customerGroupId) {
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
        $driver = $this->discountDriver();

        if (! $driver) {
            throw new InvalidArgumentException("Unknown discount type [{$this->type}].");
        }

        return $driver->calculate($this, $subtotal, $shippingCost, $lineItems);
    }

    protected function discountDriver(): ?DiscountType
    {
        $container = Container::getInstance();

        if ($container->bound(DiscountManager::class)) {
            return $container->make(DiscountManager::class)->driver($this->type);
        }

        return DiscountManager::defaults()->driver($this->type);
    }
}
