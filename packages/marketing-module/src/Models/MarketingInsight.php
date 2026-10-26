<?php

namespace Nreljed\MarketingModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nreljed\MarketingModule\Enums\MarketingInsightConfidence;
use Nreljed\MarketingModule\Enums\MarketingInsightStatus;
use Nreljed\MarketingModule\Enums\MarketingInsightType;
use Nreljed\Platform\Models\User;

/**
 * A period-scoped observation and its recommended next action.
 *
 * The evidence array is what separates a recommendation from a guess: every
 * insight stores the numbers it was derived from.
 */
class MarketingInsight extends Model
{
    protected $fillable = [
        'fingerprint',
        'period_days',
        'period_start',
        'period_end',
        'type',
        'title',
        'summary',
        'recommendation',
        'evidence',
        'confidence',
        'status',
        'generated_by',
        'applied_by',
        'applied_at',
        'metadata',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'type' => MarketingInsightType::class,
        'confidence' => MarketingInsightConfidence::class,
        'status' => MarketingInsightStatus::class,
        'evidence' => 'array',
        'applied_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function isNew(): bool
    {
        return $this->status === MarketingInsightStatus::New;
    }
}
