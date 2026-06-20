<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public static function log(AuditAction $action, int $userId, array $context = []): void
    {
        AuditLog::create([
            'action' => $action->value,
            'user_id' => $userId,
            'ip' => Request::ip() ?? 'unknown',
            'user_agent' => Request::userAgent() ?? '',
            'context' => !empty($context) ? $context : null,
        ]);
    }

    public static function getLog(int $limit = 100): array
    {
        return AuditLog::with('user')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
