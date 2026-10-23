<?php

namespace Nreljed\MarketingModule\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Nreljed\MarketingModule\Database\Factories\SocialPostFactory;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Support\PostContent;
use Nreljed\Platform\Models\User;

/**
 * One piece of content addressed to one or more connected accounts.
 *
 * The post owns the canonical content; each target owns its own delivery
 * state, attempts and result. That split is what lets a five-platform post
 * succeed on four and be retried on the fifth without duplicating the text.
 */
class SocialPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'campaign_id',
        'body',
        'media',
        'link',
        'track_clicks',
        'hashtags',
        'status',
        'scheduled_at',
        'timezone',
        'requires_approval',
        'approved_at',
        'published_at',
        'metadata',
    ];

    protected $casts = [
        'media' => 'array',
        'hashtags' => 'array',
        'metadata' => 'array',
        'status' => PostStatus::class,
        'scheduled_at' => 'datetime',
        'track_clicks' => 'boolean',
        'requires_approval' => 'boolean',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return SocialPostFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return HasMany<SocialPostTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(SocialPostTarget::class);
    }

    /**
     * @return HasManyThrough<SocialPostMetric, SocialPostTarget, $this>
     */
    public function metrics(): HasManyThrough
    {
        return $this->hasManyThrough(SocialPostMetric::class, SocialPostTarget::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(MarketingLink::class);
    }

    /**
     * Whether this post must be approved before the scheduler may publish it.
     */
    public function requiresApproval(): bool
    {
        return (bool) $this->requires_approval;
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Whether the scheduler may pick this post up at the given moment.
     */
    public function isDueAt(CarbonInterface $now): bool
    {
        if ($this->scheduled_at === null || $this->scheduled_at->greaterThan($now)) {
            return false;
        }

        if (! in_array($this->status, [PostStatus::Scheduled, PostStatus::Publishing], true)) {
            return false;
        }

        return ! $this->requiresApproval() || $this->isApproved();
    }

    /**
     * Posts the scheduler should consider at the given moment.
     *
     * @param  Builder<SocialPost>  $query
     */
    public function scopeDue(Builder $query, CarbonInterface $now): void
    {
        $query->whereIn('status', [PostStatus::Scheduled->value, PostStatus::Publishing->value])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->where(static function (Builder $query): void {
                $query->where('requires_approval', false)->orWhereNotNull('approved_at');
            });
    }

    /**
     * The platform-neutral content an adapter receives.
     */
    public function toPostContent(?string $link = null): PostContent
    {
        return PostContent::make(
            (string) ($this->body ?? ''),
            (array) ($this->media ?? []),
            $link ?? $this->link,
            array_values(array_filter((array) ($this->hashtags ?? []), 'is_string')),
        );
    }
}
