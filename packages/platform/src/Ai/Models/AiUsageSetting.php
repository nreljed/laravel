<?php

namespace Nreljed\Platform\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nreljed\Platform\Models\User;

/**
 * Per-user soft daily AI usage caps (requests + USD spend).
 *
 * Today's + all-time usage is derived from {@see AiUsageLog}; this model only
 * holds the user-settable ceiling used by the usage UI and enforcement path.
 *
 * The legacy `cap_tokens` column still exists in the table (never dropped,
 * for consumers mid-upgrade) but is ignored — token ceilings were replaced by
 * the USD spend cap because spend is the quantity users actually reason about.
 *
 * @property int $user_id
 * @property int $cap_requests
 * @property int|null $cap_cents
 * @property-read User $user
 */
class AiUsageSetting extends Model
{
    public const int DEFAULT_CAP_REQUESTS = 100;

    public const int DEFAULT_CAP_COST_CENTS = 500;

    public const int CAP_REQUESTS_MAX = 100000;

    /** $1000/day — the ceiling the UI enforces on the spend-cap field. */
    public const int CAP_COST_MAX = 100000;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'cap_requests',
        'cap_cents',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'cap_requests' => 'integer',
            'cap_cents' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
