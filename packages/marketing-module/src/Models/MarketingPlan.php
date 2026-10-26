<?php

namespace Nreljed\MarketingModule\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nreljed\MarketingModule\Enums\CampaignObjective;
use Nreljed\MarketingModule\Enums\MarketingPlanStatus;
use Nreljed\Platform\Models\User;

/**
 * An approved marketing strategy for one objective and window.
 *
 * The plan is the reviewable boundary between an AI suggestion and actual
 * campaigns, briefs or posts. Nothing downstream is created automatically.
 */
class MarketingPlan extends Model
{
    protected $fillable = [
        'name',
        'objective',
        'status',
        'audience',
        'cadence',
        'channel_mix',
        'content_pillars',
        'kpi_targets',
        'constraints',
        'starts_at',
        'ends_at',
        'approved_by',
        'approved_at',
        'generated_by',
        'metadata',
    ];

    protected $casts = [
        'objective' => CampaignObjective::class,
        'status' => MarketingPlanStatus::class,
        'channel_mix' => 'array',
        'content_pillars' => 'array',
        'kpi_targets' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'approved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<MarketingBrief, $this>
     */
    public function briefs(): HasMany
    {
        return $this->hasMany(MarketingBrief::class);
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function isApproved(): bool
    {
        return $this->status === MarketingPlanStatus::Approved && $this->approved_at !== null;
    }

    public function approve(User $user): void
    {
        $this->forceFill([
            'status' => MarketingPlanStatus::Approved,
            'approved_by' => $user->getKey(),
            'approved_at' => now(),
        ])->save();
    }

    public function archive(): void
    {
        $this->forceFill(['status' => MarketingPlanStatus::Archived])->save();
    }

    public function isLiveAt(CarbonInterface $now): bool
    {
        if ($this->starts_at !== null && $this->starts_at->greaterThan($now)) {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->greaterThanOrEqualTo($now);
    }
}
