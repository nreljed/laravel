<?php

namespace Nreljed\Platform\Deploy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class VerifyDeployToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->assertIpAllowed($request);

        $expected = config('deploy.token');

        $token = $request->bearerToken();

        // Query-string transport is a legacy fallback the consumers are
        // migrating away from. Kept configurable so deploys never break
        // mid-migration, but it is off once everyone uses the header.
        if (! $token && config('deploy.allow_query_token')) {
            $token = $request->input('token');
        }

        if (! $expected || ! is_string($token) || ! hash_equals($expected, $token)) {
            abort(403, 'Invalid or missing deploy token.');
        }

        return $next($request);
    }

    /**
     * Optionally restrict /deploy/* to known CI/runner IPs.
     */
    protected function assertIpAllowed(Request $request): void
    {
        $allowlist = (array) config('deploy.ip_allowlist');

        if ($allowlist === []) {
            return;
        }

        foreach ($allowlist as $entry) {
            if (IpUtils::checkIp((string) $request->ip(), $entry)) {
                return;
            }
        }

        abort(403, 'Deploy endpoints are not allowed from your IP.');
    }
}
