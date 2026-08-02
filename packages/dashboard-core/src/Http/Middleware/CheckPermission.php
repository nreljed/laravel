<?php

namespace Nreljed\DashboardCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        if (! $request->user() || ! $request->user()->hasPermission($slug)) {
            abort(403);
        }

        return $next($request);
    }
}
