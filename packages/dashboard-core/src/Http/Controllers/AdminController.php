<?php

namespace Nreljed\DashboardCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Nreljed\DashboardCore\Services\SystemHealthService;

class AdminController extends Controller
{
    public function settings()
    {
        return Inertia::render('dashboard/admin/settings');
    }

    public function systemHealth(SystemHealthService $systemHealth)
    {
        return Inertia::render('dashboard/admin/system-health', $systemHealth->getAll());
    }

    public function refreshSystemHealth(SystemHealthService $systemHealth): JsonResponse
    {
        Cache::forget('system_health_latest_version');
        Cache::forget('system_health_core_latest_version');

        return response()->json($systemHealth->getAll());
    }

    public function readSystemHealthLog(SystemHealthService $systemHealth, string $file): JsonResponse
    {
        $lines = request()->integer('lines', 200);

        try {
            return response()->json($systemHealth->readLog($file, $lines));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable) {
            return response()->json(['error' => 'Unable to read log file.'], 500);
        }
    }
}
