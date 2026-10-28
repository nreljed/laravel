<?php

namespace Nreljed\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        Vite::useCspNonce($nonce);
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        // Skip only in local development unless explicitly enabled (e.g. staging
        // and other non-prod environments still get the policy). In production it
        // always applies.
        if (app()->environment('local') && ! config('core.csp_enabled', false)) {
            return $response;
        }

        $csp = $this->buildPolicy($nonce);
        $response->headers->set('Content-Security-Policy', $csp);

        $endpoint = config('app.url').'/csp-report';
        $response->headers->set('Reporting-Endpoints', sprintf('csp-endpoint="%s"', $endpoint));

        return $response;
    }

    protected function buildPolicy(string $nonce): string
    {
        $appUrl = config('app.url');
        $wsUrl = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $appUrl);

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net",
            "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net data:",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' {$wsUrl}",
            "worker-src 'self' blob:",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            'report-to csp-endpoint',
        ];

        return implode('; ', $directives);
    }
}
