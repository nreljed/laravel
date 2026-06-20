<?php

namespace App\Services;

use App\Modules\ModuleRegistry;
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

            if (app()->resolved(ModuleRegistry::class)) {
                $registry = app(ModuleRegistry::class);
                $moduleData = $registry->getAllDashboardData();

                foreach ($moduleData as $slug => $data) {
                    if (isset($data['total'])) {
                        $stats[$slug] = [
                            'label' => $data['label'] ?? ucfirst($slug),
                            'icon' => $data['icon'] ?? 'FileText',
                            'value' => $data['total'],
                        ];
                    }
                }
            }

            return $stats;
        });
    }

    public function clearStatsCache(): void
    {
        Cache::forget('dashboard.stats');
    }
}
