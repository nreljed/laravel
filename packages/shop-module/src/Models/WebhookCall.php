<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookCall extends Model
{
    protected $fillable = [
        'webhook_id', 'event', 'payload', 'status', 'attempts',
        'max_attempts', 'response_status', 'response_body',
        'scheduled_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function isPending(): bool
    {
        return $this->status === 0;
    }

    public function isSuccess(): bool
    {
        return $this->status === 1;
    }

    public function isFailed(): bool
    {
        return $this->status === 2;
    }

    public function shouldRetry(): bool
    {
        return $this->status !== 1 && $this->attempts < $this->max_attempts;
    }
}
