<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Nreljed\DashboardCore\Models\User;

class DashboardService
{
    public function getStats(): array
    {
        return Cache::remember('dashboard.stats', 300, function () {
            $stats = [
                'total_users' => [
                    'label' => 'Total Users',
                    'icon' => 'Users',
                    'value' => User::count(),
                ],
                'new_users_today' => [
                    'label' => 'New Today',
                    'icon' => 'UserPlus',
                    'value' => User::whereDate('created_at', today())->count(),
                ],
            ];

            try {
                $registry = app(FeatureRegistry::class);
                $moduleData = $registry->getAllDashboardData();

                foreach ($moduleData as $slug => $data) {
                    $reserved = ['label', 'icon', 'total'];
                    $hasTotal = isset($data['total']);
                    if ($hasTotal) {
                        $stats[$slug] = [
                            'label' => $data['label'] ?? ucfirst($slug),
                            'icon' => $data['icon'] ?? 'FileText',
                            'value' => $data['total'],
                        ];
                    }
                    foreach ($data as $key => $value) {
                        if (in_array($key, $reserved, true)) {
                            continue;
                        }
                        $stats[$slug.'_'.$key] = [
                            'label' => $data['label'].' — '.ucfirst(str_replace('_', ' ', $key)),
                            'icon' => $data['icon'] ?? 'FileText',
                            'value' => $value,
                        ];
                    }
                }
            } catch (\Throwable) {
                // Feature registry unavailable — core stats only.
            }

            return $stats;
        });
    }

    public function getAnalytics(): array
    {
        return Cache::remember('dashboard.analytics', 300, function () {
            $series = [
                'users' => [
                    'label' => 'New Users',
                    'data' => $this->dailyCountSeries(User::query(), 'created_at'),
                ],
            ];

            try {
                $series = array_merge($series, app(FeatureRegistry::class)->getAllAnalyticsSeries());
            } catch (\Throwable) {
                // Feature registry unavailable — core series only.
            }

            return $series;
        });
    }

    public function clearStatsCache(): void
    {
        Cache::forget('dashboard.stats');
        Cache::forget('dashboard.analytics');
    }

    private function dailyCountSeries(Builder $query, string $column, int $days = 30): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $counts = $query
            ->where($column, '>=', $start)
            ->selectRaw("DATE({$column}) as date, COUNT(*) as count")
            ->groupBy('date')
            ->pluck('count', 'date');

        return collect(range(0, $days - 1))
            ->map(fn (int $offset) => [
                'date' => now()->subDays($days - 1 - $offset)->toDateString(),
                'count' => (int) ($counts[now()->subDays($days - 1 - $offset)->toDateString()] ?? 0),
            ])
            ->values()
            ->all();
    }
}
