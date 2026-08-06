<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Support\Facades\Cache;
use Nreljed\DashboardCore\Enums\AuditAction;
use Nreljed\DashboardCore\Models\AuditLog;

class AuditService
{
    public static function log(AuditAction $action, int $userId = 0, array $context = []): void
    {
        AuditLog::create([
            'action' => $action->value,
            'user_id' => $userId ?: null,
            'ip' => request()->ip() ?? 'unknown',
            'user_agent' => request()->userAgent() ?? '',
            'context' => ! empty($context) ? $context : null,
        ]);

        Cache::forget('audit_stats');
    }

    public static function getLog(int $limit = 100, ?string $action = null, ?int $userId = null, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = AuditLog::with('user:id,name')->latest('id');

        if ($action !== null) {
            $query->where('action', $action);
        }

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($fromDate !== null) {
            $query->where('created_at', '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->where('created_at', '<=', $toDate);
        }

        return $query->limit($limit)->get()->toArray();
    }

    public static function getByUser(int $userId, int $limit = 50): array
    {
        return AuditLog::with('user:id,name')
            ->where('user_id', $userId)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public static function getByAction(AuditAction $action, int $limit = 100): array
    {
        return AuditLog::with('user:id,name')
            ->where('action', $action->value)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public static function getStats(): array
    {
        return Cache::remember('audit_stats', 300, function () {
            $grouped = AuditLog::selectRaw('action, count(*) as total')
                ->groupBy('action')
                ->pluck('total', 'action')
                ->toArray();

            $todayTotal = AuditLog::whereDate('created_at', today())->count();

            $totalEntries = AuditLog::count();

            return [
                'grouped' => $grouped,
                'today' => $todayTotal,
                'total' => $totalEntries,
            ];
        });
    }

    public static function trimToLimit(int $maxEntries = 5000): int
    {
        $count = AuditLog::count();

        if ($count <= $maxEntries) {
            return 0;
        }

        $idsToDelete = AuditLog::orderBy('id')
            ->take($count - $maxEntries)
            ->pluck('id');

        $deleted = AuditLog::whereIn('id', $idsToDelete)->delete();

        Cache::forget('audit_stats');

        return $deleted;
    }

    public static function pruneOlderThan(int $days = 90): int
    {
        $deleted = AuditLog::where('created_at', '<', now()->subDays($days))->delete();

        Cache::forget('audit_stats');

        return $deleted;
    }

    public static function clearUserLogs(int $userId): int
    {
        $deleted = AuditLog::where('user_id', $userId)->delete();

        Cache::forget('audit_stats');

        return $deleted;
    }

    public static function getRecentActivity(int $limit = 10): array
    {
        return AuditLog::with('user:id,name')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
