<?php

namespace Nreljed\ShopModule\Http\Controllers\Api;

use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Http\Resources\ProductResource;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Services\ProductSearch;

class ProductsController extends Controller
{
    private ?ProductSearch $productSearch = null;

    private function productSearch(): ProductSearch
    {
        return $this->productSearch ??= app(ProductSearch::class);
    }
    /**
     * The catalog-visibility values this endpoint may return.
     *
     * Mirrors the storefront's own rule, so the public feed and the shop can
     * never disagree about what exists: a search-only product is findable by
     * name, and a hidden one is absent from both. The list comes from the
     * model rather than a copy of it here - that copy was the reason a value
     * added to the database could be missing from one surface and not another.
     *
     * @return list<string>
     */
    private function browsableVisibility(Request $request): array
    {
        return $this->searchTerm($request) !== null
            ? Product::SEARCH_VISIBILITIES
            : Product::LISTING_VISIBILITIES;
    }

    public function index(Request $request)
    {
        // Unpublished and unpublish-scheduled products were returned by this
        // endpoint, and `?status=draft` asked for them by name. The storefront
        // has always scoped its catalog to `published()`; the public API did
        // not, so a draft's name, description and price were readable before
        // it launched, and a scheduled product was readable before its date.
        $query = Product::published()
            ->whereIn('catalog_visibility', $this->browsableVisibility($request));

        $search = $this->searchTerm($request);

        if ($search !== null) {
            $this->productSearch()->applyTo($query, $search, ['name', 'sku']);
        }

        // No `status` filter. It is an editorial field, and honouring it from
        // an unauthenticated request turns the endpoint into a way to list
        // exactly the products the shop has decided not to publish.

        if ($request->filled('min_price') && is_numeric($request->min_price)) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price') && is_numeric($request->max_price)) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate($this->perPage($request));

        return ProductResource::collection($products);
    }

    /**
     * A search term, or null when there is nothing usable to search for.
     *
     * The term is normalised by the shared `ProductSearch` service (trimmed
     * and capped at 100 characters: the value is interpolated straight into a
     * `LIKE` pattern, and an unbounded string is an unbounded amount of work
     * for a database to scan for every anonymous caller).
     */
    private function searchTerm(Request $request): ?string
    {
        if (! $request->filled('search') || ! is_string($request->search)) {
            return null;
        }

        return $this->productSearch()->term($request->search);
    }

    /**
     * Page size, clamped to 1-100.
     *
     * A caller asking for `per_page=0` used to get an empty page rather than
     * a 422, which reads as "the shop has no products" instead of "your
     * request was wrong" - worth pinning down here.
     */
    private function perPage(Request $request): int
    {
        $requested = (int) $request->query('per_page', 20);

        return max(1, min(100, $requested ?: 20));
    }

    public function show(Product $product)
    {
        // Route-model binding resolves any product, published or not, so the
        // show endpoint leaked the same drafts the list endpoint did - one id
        // at a time instead of all at once.
        //
        // One gate, the same one the storefront page uses. This endpoint used
        // to hold a second, narrower list, so a search-only product was
        // readable in the shop and 404'd here: the same product, two different
        // answers, depending on which URL a caller had.
        if (! $product->isVisibleOnStorefront()) {
            abort(404);
        }

        return new ProductResource($product);
    }
}
