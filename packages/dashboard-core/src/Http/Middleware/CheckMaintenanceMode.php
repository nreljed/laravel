<?php

namespace Nreljed\DashboardCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Nreljed\DashboardCore\Services\SettingsService;
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

            $bypassToken = SettingsService::get('maintenance_bypass_token', '');
            if ($bypassToken !== '' && $this->isValidBypassToken($request, $bypassToken)) {
                return $next($request);
            }

            $message = SettingsService::get('maintenance_message', 'We are performing scheduled maintenance. Please check back shortly.');
            $endsAt = SettingsService::get('maintenance_ends_at', '');
            $retryAfter = 30;

            if ($endsAt !== '' && $endsAt !== null) {
                $endCarbon = Carbon::parse($endsAt);
                if ($endCarbon->isFuture()) {
                    $retryAfter = max(1, (int) abs(now()->diffInSeconds($endCarbon)));
                } else {
                    $retryAfter = 30;
                }
            }

            return response()->view('maintenance', [
                'message' => $message,
                'endsAt' => $endsAt,
            ], 503)->header('Retry-After', (string) $retryAfter);
        }

        return $next($request);
    }

    private function isValidBypassToken(Request $request, string $validToken): bool
    {
        $provided = $request->query('token') ?? $request->cookie('maintenance_bypass');

        if (! is_string($provided) || $provided === '') {
            return false;
        }

        return hash_equals($validToken, $provided);
    }
}
