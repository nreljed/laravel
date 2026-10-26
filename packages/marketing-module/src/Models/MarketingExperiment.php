<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nreljed\MarketingModule\Enums\MarketingExperimentStatus;

/**
 * A planned content test with an explicit success metric.
 */
class MarketingExperiment extends Model
{
    protected $fillable = [
        'marketing_brief_id',
        'campaign_id',
        'name',
        'hypothesis',
        'success_metric',
        'status',
        'variants',
        'winner',
        'started_at',
        'ended_at',
        'metadata',
    ];

    protected $casts = [
        'status' => MarketingExperimentStatus::class,
        'variants' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function brief(): BelongsTo
    {
        return $this->belongsTo(MarketingBrief::class, 'marketing_brief_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
