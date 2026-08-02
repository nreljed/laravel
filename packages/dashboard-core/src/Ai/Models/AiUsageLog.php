<?php

namespace Nreljed\DashboardCore\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nreljed\DashboardCore\Models\User;

/**
 * Tracks AI usage per user for billing and analytics.
 *
 * @property int $user_id
 * @property string $agent_class
 * @property string $provider
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int|null $cost_cents
 */
class AiUsageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'agent_class',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'cost_cents',
        'created_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_cents' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the estimated cost as a formatted dollar string.
     */
    public function getEstimatedCostAttribute(): ?string
    {
        if ($this->cost_cents === null) {
            return null;
        }

        return '$'.number_format($this->cost_cents / 100, 4);
    }

    /**
     * Get total tokens (input + output).
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }
}
