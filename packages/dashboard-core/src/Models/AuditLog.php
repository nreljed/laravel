<?php

namespace Nreljed\DashboardCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $action
 * @property int $user_id
 * @property string $ip
 * @property string $user_agent
 * @property array|null $context
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action',
        'user_id',
        'ip',
        'user_agent',
        'context',
        'created_at',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
