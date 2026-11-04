<?php

namespace Nreljed\Platform\Contracts\Capabilities;

/**
 * The feature contributes public URLs to the shared `/sitemap.xml`.
 */
interface HasSitemap
{
    /**
     * Sitemap URL entries. Each entry is a map with `loc`, `changefreq`,
     * `priority` and optional `lastmod` (an ISO date string), matching the base
     * SitemapController's `url()` shape. Return a relative path (e.g. `/store`,
     * `/store/my-product`) — the controller prefixes the site base URL.
     *
     * @return array<int, array{loc: string, changefreq: string, priority: string, lastmod?: string|null}>
     */
    public function getSitemapUrls(): array;
}
