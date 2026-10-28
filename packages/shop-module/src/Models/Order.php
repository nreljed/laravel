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
        'order_number', 'user_id', 'cart_id', 'status', 'confirmation_token',
        'subtotal', 'tax_total', 'shipping_total', 'discount_total', 'total', 'total_refunded',
        'shipping_method_id', 'shipping_method_name',
        'currency', 'exchange_rate', 'payment_method', 'payment_status',
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
        'exchange_rate' => 'float',
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

    /**
     * The shipping method this order was priced with.
     *
     * Nullable all the way through, and deliberately so: the FK nulls when the
     * method is deleted, and orders placed before the column existed have no
     * id at all. `shipping_method_name` is the snapshot that outlives both, so
     * anything reading a past order's shipping should fall back to it rather
     * than treat a null relation as an order that was never shipped.
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * The cart this order was quoted from. Set on provisional (pre-payment)
     * orders so checkout can prove the draft belongs to the shopper claiming
     * it; the cart is resolved from the session or the authenticated user.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * The secret that proves a visitor may see this order without an account.
     *
     * Generated once at creation and carried in the confirmation link, so it
     * survives the shopper closing the tab, bookmarking the page, or reading
     * the link out of their inbox weeks later - none of which a session-bound
     * check would survive.
     */
    public static function newConfirmationToken(): string
    {
        return Str::random(64);
    }

    /**
     * Whether `$token` is this order's confirmation token.
     *
     * Compared in constant time: a length- or prefix-dependent comparison would
     * let someone recover a valid token one character at a time by timing
     * requests against a sequential id, which is the same enumeration problem
     * the token exists to remove.
     */
    public function hasValidConfirmationToken(?string $token): bool
    {
        if ($token === null || $token === '' || $this->confirmation_token === null) {
            return false;
        }

        return hash_equals((string) $this->confirmation_token, $token);
    }

    /**
     * Whether `$user` placed this order.
     */
    public function belongsToUser(?User $user): bool
    {
        return $user !== null
            && $this->user_id !== null
            && (int) $this->user_id === (int) $user->id;
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

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
