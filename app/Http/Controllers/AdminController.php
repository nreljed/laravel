<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

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

        return response()->json($systemHealth->getAll());
    }
}
