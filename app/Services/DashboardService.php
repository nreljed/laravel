<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

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

            if (app()->resolved(FeatureRegistry::class)) {
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
            }

            return $stats;
        });
    }

    public function getAnalytics(): array
    {
        return Cache::remember('dashboard.analytics', 300, function () {
            return $this->emptyAnalytics();
        });
    }

    public function clearStatsCache(): void
    {
        Cache::forget('dashboard.stats');
        Cache::forget('dashboard.analytics');
    }

    private function emptyAnalytics(): array
    {
        return [
            'kpis' => [],
            'revenue_over_time' => [],
            'total_revenue' => 0.0,
            'total_customers' => 0,
        ];
    }

    private function pctChange(float|int $previous, float|int $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
