<?php

namespace Nreljed\DashboardCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nreljed\DashboardCore\Models\IpAccessRule;
use Symfony\Component\HttpFoundation\Response;

class CheckIpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('deploy/*')) {
            return $next($request);
        }

        if ($request->user() && $request->user()->hasRole('admin')) {
            return $next($request);
        }

        $ip = $request->ip();

        if ($ip && ! IpAccessRule::isIpAllowed($ip)) {
            abort(403, 'Your IP address is not authorized to access this resource.');
        }

        return $next($request);
    }
}
