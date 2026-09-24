<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    public const TYPE_BILLING = 'billing';
    public const TYPE_SHIPPING = 'shipping';

    protected $fillable = [
        'code', 'customer_id', 'type', 'line1', 'line2',
        'city', 'postal_code', 'country_code', 'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}