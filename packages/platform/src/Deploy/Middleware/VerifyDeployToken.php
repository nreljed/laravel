<?php

namespace Nreljed\Platform\Deploy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDeployToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input('token') ?: $request->bearerToken();
        $expected = config('deploy.token');

        if (! $expected || ! hash_equals((string) $expected, (string) $token)) {
            abort(403, 'Invalid or missing deploy token.');
        }

        return $next($request);
    }
}
