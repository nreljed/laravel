<?php

namespace App\Http\Middleware;

use App\Modules\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModuleEnabled
{
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        if (! app()->resolved(ModuleRegistry::class) || ! app(ModuleRegistry::class)->isEnabled($slug)) {
            abort(404);
        }

        return $next($request);
    }
}
