<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shop-reported conversion attributed to a tracked link.
 *
 * The module stores the reference and reported value only. Orders, revenue
 * recognition and customer data remain owned by the shop.
 */
class MarketingConversion extends Model
{
    protected $fillable = [
        'marketing_link_id',
        'marketing_link_click_id',
        'source',
        'event_type',
        'external_reference',
        'idempotency_key',
        'value',
        'currency',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(MarketingLink::class, 'marketing_link_id');
    }

    public function click(): BelongsTo
    {
        return $this->belongsTo(MarketingLinkClick::class, 'marketing_link_click_id');
    }
}
