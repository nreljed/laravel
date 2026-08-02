<?php

namespace Nreljed\DashboardCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nreljed\DashboardCore\Services\SettingsService;
use Symfony\Component\HttpFoundation\Response;

class ConfigureSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                $lifetime = (int) SettingsService::get('session_lifetime', 120);
                if ($lifetime > 0) {
                    config()->set('session.lifetime', $lifetime);
                }
            }
        } catch (\Throwable) {
            // Settings table may not exist yet
        }

        return $next($request);
    }
}
