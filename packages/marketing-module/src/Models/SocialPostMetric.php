<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One daily engagement snapshot for one delivery.
 *
 * Missing platform metrics stay null: null means the provider did not expose
 * the number, while zero means the provider reported a real zero.
 */
class SocialPostMetric extends Model
{
    protected $fillable = [
        'social_post_target_id',
        'metric_date',
        'captured_at',
        'impressions',
        'reach',
        'likes',
        'comments',
        'shares',
        'saves',
        'clicks',
        'video_views',
        'replies',
        'reposts',
        'quotes',
        'bookmarks',
        'profile_visits',
        'custom',
        'raw',
        'source',
    ];

    protected $casts = [
        'metric_date' => 'date',
        'captured_at' => 'datetime',
        'impressions' => 'integer',
        'reach' => 'integer',
        'likes' => 'integer',
        'comments' => 'integer',
        'shares' => 'integer',
        'saves' => 'integer',
        'clicks' => 'integer',
        'video_views' => 'integer',
        'replies' => 'integer',
        'reposts' => 'integer',
        'quotes' => 'integer',
        'bookmarks' => 'integer',
        'profile_visits' => 'integer',
        'custom' => 'array',
        'raw' => 'array',
    ];

    public function target(): BelongsTo
    {
        return $this->belongsTo(SocialPostTarget::class, 'social_post_target_id');
    }

    /**
     * Total interactions across the canonical engagement counters.
     */
    public function engagements(): ?int
    {
        $values = array_filter([
            $this->likes,
            $this->comments,
            $this->shares,
            $this->saves,
            $this->replies,
            $this->reposts,
            $this->quotes,
            $this->bookmarks,
        ], static fn (?int $value): bool => $value !== null);

        return $values === [] ? null : (int) array_sum($values);
    }

    public function engagementRate(): ?float
    {
        return $this->rate($this->engagements(), $this->impressions);
    }

    public function reachEngagementRate(): ?float
    {
        return $this->rate($this->engagements(), $this->reach);
    }

    public function clickThroughRate(): ?float
    {
        return $this->rate($this->clicks, $this->impressions);
    }

    public function saveRate(): ?float
    {
        return $this->rate($this->saves, $this->impressions);
    }

    private function rate(?int $numerator, ?int $denominator): ?float
    {
        if ($numerator === null || $denominator === null || $denominator === 0) {
            return null;
        }

        return $numerator / $denominator;
    }
}
