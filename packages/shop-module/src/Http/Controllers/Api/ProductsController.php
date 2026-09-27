<?php

namespace Nreljed\ShopModule\Http\Controllers\Api;

use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Http\Resources\ProductResource;
use Nreljed\ShopModule\Models\Product;

class ProductsController extends Controller
{
    /**
     * The catalog visibility values a shopper may browse.
     *
     * Mirrors the storefront's own filter. `search` exists so a product can be
     * reachable by direct link without appearing in listings, and `hidden`
     * keeps it out of the API entirely; neither belongs in a public feed.
     *
     * @var list<string>
     */
    private const BROWSABLE_VISIBILITY = ['visible', 'catalog'];

    public function index(Request $request)
    {
        // Unpublished and unpublish-scheduled products were returned by this
        // endpoint, and `?status=draft` asked for them by name. The storefront
        // has always scoped its catalog to `published()`; the public API did
        // not, so a draft's name, description and price were readable before
        // it launched, and a scheduled product was readable before its date.
        $query = Product::published()
            ->whereIn('catalog_visibility', self::BROWSABLE_VISIBILITY);

        $search = $this->searchTerm($request);

        if ($search !== null) {
            $query->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
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
     * Capped at 100 characters: the value is interpolated straight into a
     * `LIKE` pattern, and an unbounded string is an unbounded amount of work
     * for a database to scan for every anonymous caller.
     */
    private function searchTerm(Request $request): ?string
    {
        if (! $request->filled('search') || ! is_string($request->search)) {
            return null;
        }

        $term = trim($request->search);

        if ($term === '') {
            return null;
        }

        return mb_substr($term, 0, 100);
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
        if (! $product->isVisibleOnStorefront() || ! in_array($product->catalog_visibility, self::BROWSABLE_VISIBILITY, true)) {
            abort(404);
        }

        return new ProductResource($product);
    }
}
