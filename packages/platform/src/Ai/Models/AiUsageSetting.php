<?php

namespace Nreljed\Platform\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nreljed\Platform\Models\User;

/**
 * Per-user soft daily AI usage caps (requests + tokens).
 *
 * Today's + all-time usage is derived from {@see AiUsageLog}; this model only
 * holds the user-settable ceiling used by the usage UI and enforcement path.
 *
 * @property int $user_id
 * @property int $cap_requests
 * @property int $cap_tokens
 * @property-read User $user
 */
class AiUsageSetting extends Model
{
    public const int DEFAULT_CAP_REQUESTS = 100;

    public const int DEFAULT_CAP_TOKENS = 250000;

    public const int CAP_REQUESTS_MAX = 100000;

    public const int CAP_TOKENS_MAX = 1000000000;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'cap_requests',
        'cap_tokens',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'cap_requests' => 'integer',
            'cap_tokens' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
