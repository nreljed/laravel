<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Database\Factories\OrderFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number', 'user_id', 'status',
        'subtotal', 'tax_total', 'shipping_total', 'discount_total', 'total', 'total_refunded',
        'currency', 'payment_method', 'payment_status',
        'notes', 'billing_address', 'shipping_address',
        'customer_email', 'customer_phone', 'customer_name',
        'processed_by', 'paid_at', 'shipped_at', 'refunded_at', 'refund_amount', 'refund_notes',
        'payment_intent_id',
        'applied_coupons',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'refunded_at' => 'datetime',
        'refund_amount' => 'decimal:2',
        'total_refunded' => 'decimal:2',
        'applied_coupons' => 'array',
    ];

    protected static function newFactory(): Factory
    {
        return OrderFactory::new();
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Order $order) {
            if (! $order->order_number) {
                $order->order_number = 'ORD-'.strtoupper(Str::random(8));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(OrderAction::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
