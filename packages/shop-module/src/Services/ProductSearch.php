<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Nreljed\ShopModule\Contracts\ProductSearchDriver;

/**
 * The single entry point every product-searching surface goes through.
 *
 * The storefront (`applyFilters`), the public catalog API and the admin
 * product list all used to build their own `LIKE '%...%'` predicates, which
 * let the term handling drift (the API trimmed and capped, the admin and
 * storefront did not, and none escaped a wildcard). The driver is resolved
 * from `config('shop.search.driver')`, so swapping the matching engine later
 * touches one config key, not three controllers.
 */
class ProductSearch
{
    public function __construct(private readonly ProductSearchDriver $driver) {}

    /**
     * A usable search term, or null when the raw input carries nothing to
     * search for.
     *
     * @return non-empty-string|null
     */
    public function term(string $query): ?string
    {
        return $this->driver->normalize($query);
    }

    /**
     * Constrain a product query to rows matching $term. Returns the query
     * unchanged when the term is null, so callers can normalise once and
     * always pass the result through.
     *
     * @param  list<string>  $fields
     */
    public function applyTo(EloquentBuilder $query, ?string $term, array $fields): EloquentBuilder
    {
        if ($term === null) {
            return $query;
        }

        return $this->driver->constrain($query, $term, $fields);
    }
}