<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceInertiaCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $uri = $request->getRequestUri();

        if (strpos($uri, '/dashboard') !== 0) {
            return $next($request);
        }

        $isDocumentNavigation = false;

        $dest = $request->header('Sec-Fetch-Dest');
        $mode = $request->header('Sec-Fetch-Mode');

        if ($dest === 'document' || $mode === 'navigate') {
            $isDocumentNavigation = true;
        } elseif ($dest === null && $mode === null) {
            if (! $request->hasHeader('X-Requested-With') || $request->header('X-Requested-With') !== 'XMLHttpRequest') {
                $isDocumentNavigation = true;
            }
        }

        if ($isDocumentNavigation && $request->hasHeader('X-Inertia')) {
            $request->headers->remove('X-Inertia');
            $request->headers->remove('x-inertia');
        }

        $response = $next($request);

        if ($request->header('X-Inertia')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Vary', 'X-Inertia');
        } else {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->headers->set('Vary', 'X-Inertia');
        }

        return $response;
    }
}
