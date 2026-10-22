<?php

namespace Nreljed\MarketingModule\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nreljed\MarketingModule\Database\Factories\SocialPostTargetFactory;
use Nreljed\MarketingModule\Enums\DeliveryStatus;

/**
 * One post -> one account delivery.
 *
 * Retry state lives here rather than on the post so a failure on one platform
 * never rewinds or blocks the deliveries that already succeeded.
 */
class SocialPostTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'social_post_id',
        'social_account_id',
        'status',
        'attempts',
        'last_attempted_at',
        'next_attempt_at',
        'external_post_id',
        'external_url',
        'error_code',
        'error_message',
        'published_at',
    ];

    protected $casts = [
        'status' => DeliveryStatus::class,
        'attempts' => 'integer',
        'last_attempted_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return SocialPostTargetFactory::new();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'social_account_id');
    }

    /**
     * Whether this delivery should be attempted at the given moment.
     */
    public function isDueAt(CarbonInterface $now): bool
    {
        if ($this->status !== DeliveryStatus::Pending) {
            return false;
        }

        return $this->next_attempt_at === null || $this->next_attempt_at->lessThanOrEqualTo($now);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isPublished(): bool
    {
        return $this->status === DeliveryStatus::Published;
    }
}
