<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nreljed\MarketingModule\Enums\CampaignObjective;
use Nreljed\MarketingModule\Enums\MarketingBriefStatus;
use Nreljed\Platform\Models\User;

/**
 * An editable brief for one piece of planned content.
 *
 * The brief holds the intent; the composer still owns the actual post, its
 * targets and its approval state.
 */
class MarketingBrief extends Model
{
    protected $fillable = [
        'marketing_plan_id',
        'title',
        'objective',
        'audience',
        'key_message',
        'channels',
        'status',
        'due_at',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'objective' => CampaignObjective::class,
        'channels' => 'array',
        'status' => MarketingBriefStatus::class,
        'due_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MarketingPlan::class, 'marketing_plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<MarketingExperiment, $this>
     */
    public function experiments(): HasMany
    {
        return $this->hasMany(MarketingExperiment::class);
    }
}
