<?php

namespace Nreljed\ShopModule\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Nreljed\ShopModule\Services\UrlAliasService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers a legacy storefront path with a 301 to the target's canonical URL
 * (PR 11A).
 *
 * Runs on the guest storefront group. A GET whose path matches a `url_aliases`
 * row is a renamed product or category, so the old link should land on the new
 * one rather than burn an index with a 404. Anything else - no row, a response
 * that already matched a route, a self-referencing alias, a non-GET - is
 * handed straight to the next middleware.
 */
class RedirectLegacyUrlAliases
{
    public function __construct(
        protected UrlAliasService $aliasService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        // A consumer mid-upgrade can boot the module's routes before its
        // migrations land; skip the work rather than fail the whole request.
        if (! Schema::hasTable('url_aliases')) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');

        $target = $this->aliasService->resolve($path);

        if ($target === null) {
            return $next($request);
        }

        $canonical = $this->aliasService->pathFor($target);

        if ($canonical === $path) {
            return $next($request);
        }

        return new RedirectResponse(
            $request->getSchemeAndHttpHost().$canonical,
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }
}