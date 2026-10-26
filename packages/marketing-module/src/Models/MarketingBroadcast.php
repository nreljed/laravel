<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nreljed\MarketingModule\Enums\BroadcastChannel;
use Nreljed\MarketingModule\Enums\BroadcastStatus;

class MarketingBroadcast extends Model
{
    protected $table = 'marketing_broadcasts';

    protected $fillable = [
        'name',
        'channel',
        'subject',
        'body_html',
        'body_text',
        'status',
        'segment_id',
        'tag_id',
        'scheduled_at',
        'sent_at',
        'recipient_count',
        'delivered_count',
        'failed_count',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'channel' => BroadcastChannel::class,
        'status' => BroadcastStatus::class,
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'recipient_count' => 'integer',
        'delivered_count' => 'integer',
        'failed_count' => 'integer',
        'metadata' => 'array',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    /**
     * @return HasMany<MarketingBroadcastRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(MarketingBroadcastRecipient::class, 'marketing_broadcast_id');
    }
}
