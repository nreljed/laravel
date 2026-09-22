<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Nreljed\Platform\Models\User;

class Shipment extends Model
{
    protected $fillable = [
        'order_id', 'shipment_number', 'carrier', 'tracking_number',
        'status', 'tracking_url', 'shipped_at', 'delivered_at',
        'notes', 'created_by',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Shipment $shipment) {
            if (! $shipment->shipment_number) {
                $shipment->shipment_number = 'SHP-'.strtoupper(Str::random(8));
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isShipped(): bool
    {
        return $this->status === 'shipped' || $this->status === 'delivered';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }
}
