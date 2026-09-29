<?php

namespace Nreljed\ShopModule\Contracts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Product search abstraction.
 *
 * A driver implements the matching strategy behind the storefront, the public
 * catalog API and the admin product list. The default database driver scans
 * the catalogue columns with a LIKE pattern; a future driver can hand the
 * term to a search engine (Scout/Meilisearch) and constrain the query to the
 * ids it returns without the callers knowing the difference.
 *
 * Every surface that searches products goes through the `ProductSearch`
 * service, so a term is normalised once and a LIKE wildcard can never widen
 * a match anywhere.
 */
interface ProductSearchDriver
{
    /**
     * Normalize a raw query string into the term that is actually searched.
     *
     * Trims surrounding whitespace and caps the length, because the term is
     * interpolated into a LIKE pattern and an unbounded string is unbounded
     * scan work per caller. Returns null when the input carries nothing
     * searchable.
     *
     * @return non-empty-string|null
     */
    public function normalize(string $query): ?string;

    /**
     * Constrain a product query to rows matching $term in any of $fields.
     *
     * @param  EloquentBuilder  $query
     * @param  non-empty-string  $term
     * @param  list<string>  $fields
     */
    public function constrain(EloquentBuilder $query, string $term, array $fields): EloquentBuilder;
}