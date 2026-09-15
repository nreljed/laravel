<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nreljed\Platform\Models\User;

class OrderRefund extends Model
{
    protected $fillable = [
        'order_id', 'items', 'shipping_refund', 'total', 'reason', 'created_by',
    ];

    protected $casts = [
        'items' => 'array',
        'shipping_refund' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
