<?php

namespace Nreljed\Platform\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeployRelease extends Model
{
    use HasFactory;

    protected $table = 'deploy_releases';

    protected $fillable = [
        'tag',
        'status',
        'error',
        'deployed_by',
        'deployed_at',
        'rolled_back_by',
        'rolled_back_at',
    ];

    protected $casts = [
        'deployed_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ROLLED_BACK = 'rolled-back';

    public const STATUS_FAILED = 'failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'deployed_by');
    }
}
