<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (strpos($request->getRequestUri(), '/dashboard') !== 0) {
            return $response;
        }

        $isDev = file_exists(public_path('hot'));
        $csp = $this->buildPolicy($isDev);
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }

    protected function buildPolicy(bool $isDev): string
    {
        $scriptSrc = "script-src 'self' 'unsafe-inline'";
        if ($isDev) {
            $scriptSrc .= " 'unsafe-eval'";
        }

        $directives = [
            "default-src 'self'",
            $scriptSrc,
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net",
            "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net data:",
            "img-src 'self' data: blob: https:",
            "connect-src 'self'".($isDev ? ' ws://* wss://*' : ''),
            "worker-src 'self' blob:",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }
}
