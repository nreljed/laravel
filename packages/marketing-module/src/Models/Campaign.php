<?php

namespace Nreljed\MarketingModule\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nreljed\MarketingModule\Database\Factories\CampaignFactory;
use Nreljed\MarketingModule\Enums\CampaignObjective;
use Nreljed\MarketingModule\Enums\CampaignStatus;

/**
 * A named grouping of posts around one objective.
 *
 * The campaign owns the reporting boundary, not the content: a post keeps its
 * own schedule, deliveries and approval state, and can move between campaigns
 * without losing any of them.
 */
class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketing_plan_id',
        'name',
        'description',
        'objective',
        'status',
        'starts_at',
        'ends_at',
        'metadata',
    ];

    protected $casts = [
        'objective' => CampaignObjective::class,
        'status' => CampaignStatus::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function newFactory(): Factory
    {
        return CampaignFactory::new();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MarketingPlan::class, 'marketing_plan_id');
    }

    /**
     * @return HasMany<SocialPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class);
    }

    /**
     * Whether the campaign's window includes the given moment.
     */
    public function isLiveAt(CarbonInterface $now): bool
    {
        if ($this->starts_at !== null && $this->starts_at->greaterThan($now)) {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->greaterThanOrEqualTo($now);
    }
}
