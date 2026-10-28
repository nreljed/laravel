<?php

namespace Nreljed\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Nreljed\Platform\Services\SystemHealthService;

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

    public function logViewer(SystemHealthService $systemHealth, ?string $file = null)
    {
        $info = $systemHealth->getLogInfo();
        $files = $info['files'];
        $names = array_column($files, 'name');
        $selected = $file !== null && in_array($file, $names, true) ? $file : ($names[0] ?? null);

        $perPage = request()->integer('per_page', 200);
        $page = request()->integer('page', 1);

        $log = $selected
            ? $systemHealth->readLogPage($selected, $perPage, $page)
            : [
                'name' => null,
                'size' => null,
                'modified' => null,
                'lines' => [],
                'page' => 1,
                'per_page' => $perPage,
                'total_lines' => 0,
                'total_pages' => 1,
                'truncated' => false,
            ];

        return Inertia::render('dashboard/admin/logs', [
            'channel' => $info['channel'],
            'level' => $info['level'],
            'driver' => $info['driver'],
            'path' => $info['path'],
            'files' => $files,
            'selected' => $selected,
            'log' => $log,
        ]);
    }

    public function downloadSystemHealthLog(SystemHealthService $systemHealth, string $file)
    {
        try {
            return $systemHealth->downloadLog($file);
        } catch (\InvalidArgumentException $e) {
            abort(400, $e->getMessage());
        }
    }
}
