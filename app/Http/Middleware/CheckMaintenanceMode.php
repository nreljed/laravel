<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $maintenanceMode = SettingsService::get('maintenance_mode', '0');

        if ($maintenanceMode === '1') {
            if ($request->user() && $request->user()->hasRole('admin')) {
                return $next($request);
            }

            $message = SettingsService::get('maintenance_message', 'We are performing scheduled maintenance. Please check back shortly.');

            return response()->view('maintenance', [
                'message' => $message,
            ], 503);
        }

        return $next($request);
    }
}
