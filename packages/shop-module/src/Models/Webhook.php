<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Webhook extends Model
{
    protected $fillable = [
        'name', 'url', 'secret', 'events', 'is_active',
        'headers', 'timeout', 'retry_attempts', 'retry_delay',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'headers' => 'array',
            'timeout' => 'integer',
            'retry_attempts' => 'integer',
            'retry_delay' => 'integer',
        ];
    }

    public function calls(): HasMany
    {
        return $this->hasMany(WebhookCall::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function handlesEvent(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    public function generateSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }

    protected static function booted(): void
    {
        static::creating(function (Webhook $webhook) {
            if (empty($webhook->secret)) {
                $webhook->secret = Str::random(32);
            }
        });
    }
}
