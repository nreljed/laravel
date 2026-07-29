<?php

namespace App\Http\Middleware;

use App\Models\IpAccessRule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
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
