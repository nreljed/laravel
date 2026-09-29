<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Http\Resources\StorefrontProductResource;
use Nreljed\ShopModule\Http\Resources\StorefrontVariationResource;
use Nreljed\ShopModule\Models\Attribute;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductBrand;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Models\ProductFieldValue;
use Nreljed\ShopModule\Models\ProductReview;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\CustomerPricingContext;
use Nreljed\ShopModule\Services\PriceResolver;
use Nreljed\ShopModule\Services\ProductSearch;

class StorefrontController extends Controller
{
    private ?ProductSearch $productSearch = null;

    public function __construct(
        protected CurrencyService $currencyService,
        protected PriceResolver $priceResolver,
        protected CustomerPricingContext $pricingContext,
    ) {}

    protected function productSearch(): ProductSearch
    {
        return $this->productSearch ??= app(ProductSearch::class);
    }

    /**
     * Customer group whose price overrides apply to the current shopper, or
     * null for public pricing (PR 10C).
     */
    protected function priceGroupId(): ?int
    {
        return $this->pricingContext->groupId();
    }

    public function index(Request $request)
    {
        return Inertia::render('shop/storefront/home', [
            'homepage' => $this->homepageData($request),
        ]);
    }

    public function products(Request $request)
    {
        $query = $this->baseQuery($this->visibilitiesFor($request));
        $this->applyFilters($query, $request);

        return $this->renderCatalog($request, $query);
    }

    public function show(Request $request, Product $product)
    {
        if (! $product->isVisibleOnStorefront()) {
            abort(404);
        }

        $product->load([
            'categories',
            'reviews' => fn ($q) => $q->approved()->with('user')->latest()->limit(10),
            'variationAttributes.attribute.terms',
            'productAttributes' => fn ($q) => $q->where('visible', true)->orderBy('position')->with('attribute.terms'),
            'downloads' => fn ($q) => $q->orderBy('sort_order'),
            'productFieldValues.field',
            ...$this->groupedItems(),
        ]);

        $visibleAttributes = $product->productAttributes
            ->filter(fn ($pa) => $pa->visible)
            ->map(fn ($pa) => [
                'name' => $pa->name,
                'display_type' => $pa->attribute?->display_type ?? 'text',
                'terms' => collect($pa->terms)->map(fn ($t) => [
                    'name' => $t['name'],
                    'swatch_value' => $t['swatch_value'] ?? null,
                ])->values(),
            ])->values();

        $specifications = $product->productFieldValues
            ->filter(fn (ProductFieldValue $value) => $value->field?->show_on_storefront)
            ->map(fn (ProductFieldValue $value) => [
                'name' => $value->field->name,
                'value' => $value->formattedValue(),
            ])
            ->filter(fn (array $specification) => $specification['value'] !== null)
            ->values();

        $attributes = [];
        $variations = collect();
        $defaultRange = $product->price_range;
        $priceRange = ['min' => (float) $defaultRange['min'], 'max' => (float) $defaultRange['max']];
        $defaultVariation = null;

        $currency = $this->currencyService->storefrontCode();

        if ($product->type === 'variable') {
            $variations = $product->variations()
                ->visible()
                ->active()
                ->orderBy('sort_order')
                ->get();

            $attributes = $product->variationAttributes->map(fn ($pa) => [
                'id' => $pa->attribute_id ?? $pa->id,
                'name' => $pa->name,
                'slug' => Str::slug($pa->name),
                'display_type' => $pa->attribute?->display_type ?? 'text',
                'terms' => collect($pa->terms)->map(fn ($t) => [
                    'id' => $t['id'] ?? ('custom_'.$t['slug']),
                    'name' => $t['name'],
                    'slug' => $t['slug'],
                    'swatch_value' => $t['swatch_value'] ?? null,
                ])->values(),
            ])->values();

            $defaultVariation = $product->getDefaultVariation();
        }

        foreach ($product->groupedItems as $child) {
            $this->priceResolver->hydrate($child, $currency, $this->priceGroupId());
        }

        foreach ($variations as $variation) {
            $this->priceResolver->hydrate($variation, $currency, $this->priceGroupId());
        }

        $this->priceResolver->hydrate($product, $currency, $this->priceGroupId());

        if ($defaultVariation !== null) {
            $this->priceResolver->hydrate($defaultVariation, $currency, $this->priceGroupId());
        }

        $related = $this->relatedProducts($product);

        foreach ($related as $relatedProduct) {
            $this->priceResolver->hydrate($relatedProduct, $currency, $this->priceGroupId());
        }

        if ($product->type === 'variable' && $variations->isNotEmpty()) {
            $activePrices = $variations
                ->pluck('active_price')
                ->filter(fn ($p) => $p !== null)
                ->map(fn ($p) => (float) $p);

            if ($activePrices->isNotEmpty()) {
                $priceRange = ['min' => $activePrices->min(), 'max' => $activePrices->max()];
            }
        } else {
            // Variable products use the variation prices above; everything else -
            // grouped bundles in particular, which own no price - uses the model's
            // derived range, which falls back to the bundled item prices.
            $range = $product->price_range;
            $priceRange = ['min' => (float) $range['min'], 'max' => (float) $range['max']];
        }

        // Every one of these went to the browser as a raw model before, which
        // published `cost_price` and the rest of the internal columns to
        // anonymous shoppers. The allowlist resources are the storefront's
        // contract with `ProductDetail.tsx`.
        return Inertia::render('shop/storefront/productdetail', [
            'product' => StorefrontProductResource::make($product)->resolve($request),
            'related' => StorefrontProductResource::collection($related)->resolve($request),
            'variations' => StorefrontVariationResource::collection($variations)->resolve($request),
            'attributes' => $attributes,
            'priceRange' => $priceRange,
            'defaultVariation' => $defaultVariation !== null
                ? StorefrontVariationResource::make($defaultVariation)->resolve($request)
                : null,
            'visibleAttributes' => $visibleAttributes,
            'specifications' => $specifications,
            'userReview' => $this->currentUserReview($request, $product),
        ]);
    }

    /**
     * The signed-in shopper's own review of this product, if any.
     *
     * The storefront shows approved reviews to everyone, so without this the
     * page could not tell a shopper who had already reviewed from one who had
     * not, and the form would invite a submission the controller then refuses
     * as a duplicate.
     */
    private function currentUserReview(Request $request, Product $product): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $review = ProductReview::where('product_id', $product->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $review) {
            return null;
        }

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'content' => $review->content,
            'is_approved' => $review->is_approved,
        ];
    }

    /**
     * A category page (PR 11D).
     *
     * The grid keeps listing only the category's own products - browsing a
     * parent does not silently merge its descendants' catalog into it. What
     * becomes hierarchical is the navigation around that grid: the trail above
     * it, the browseable children offered beneath its heading, and the nested
     * sidebar tree `renderCatalog` builds from the whole taxonomy.
     */
    public function category(Request $request, string $slug)
    {
        $category = ProductCategory::where('slug', $slug)->firstOrFail();

        $query = $this->baseQuery($this->visibilitiesFor($request))
            ->whereHas('categories', fn ($c) => $c->where('slug', $slug));
        $this->applyFilters($query, $request);

        return $this->renderCatalog($request, $query, [
            'activeCategory' => [
                ...$category->only('id', 'name', 'slug', 'parent_id'),
                'children' => $this->categoryChildren($category),
            ],
            'breadcrumbs' => $this->categoryBreadcrumbs($category),
        ]);
    }

    /**
     * The ancestry of a category, outermost first, for a breadcrumb trail.
     *
     * The active category is the last entry; an inactive ancestor is skipped -
     * it is not browseable, so it has no place in the trail either. The visited
     * guard is for legacy taxonomies that managed to point parents at their own
     * descendants.
     *
     * @return list<array{name: string, slug: string}>
     */
    protected function categoryBreadcrumbs(ProductCategory $category): array
    {
        $chain = [];
        $visited = [];

        for ($current = $category; $current !== null && ! isset($visited[$current->id]); $current = $current->parent) {
            $visited[$current->id] = true;

            if ($current->is_active) {
                $chain[] = $current->only('name', 'slug');
            }
        }

        return array_reverse($chain);
    }

    /**
     * The browseable sub-categories of a category, offered as rails under its
     * heading and beside the sidebar tree.
     *
     * A child needs a product the grid can list; `hidden` and `search`-only
     * stock stays out for the same reason it stays out of the facet list. A
     * `search`-only product would be fair game elsewhere - it is a real,
     * buyable product - but the count here sits beside a link to the child's
     * own grid, which never lists it.
     *
     * @return list<array{id: int, name: string, slug: string, products_count: int}>
     */
    protected function categoryChildren(ProductCategory $category): array
    {
        return $category->children()
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->published()->inListing()])
            ->orderBy('name')
            ->get()
            ->filter(fn ($c) => $c->products_count > 0)
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'products_count' => $c->products_count,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Build a browseable taxonomy tree from a flat list of categories.
     *
     * A category is a root when its parent is absent from the list - either it
     * has none, or the parent did not survive the caller's filter. A child
     * whose parent does survive is nested under it; a child whose parent does
     * not is offered at the top level, because the alternative - hiding
     * something the grid can list - would prune a browseable category from the
     * sidebar entirely.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, ProductCategory>  $categories
     * @return list<array{id: int, name: string, slug: string, children: list<array>}>
     */
    protected function categoryTree(Collection $categories): array
    {
        $presentParentIds = $categories->pluck('id')->all();

        return $categories
            ->reject(fn (ProductCategory $category): bool => $category->parent_id !== null
                && in_array($category->parent_id, $presentParentIds, true))
            ->values()
            ->map(fn (ProductCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'children' => $this->categoryTreeChildren($categories, $category->id),
            ])
            ->toArray();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ProductCategory>  $categories
     * @return list<array{id: int, name: string, slug: string, children: list<array>}>
     */
    protected function categoryTreeChildren(Collection $categories, int $parentId): array
    {
        return $categories
            ->where('parent_id', $parentId)
            ->values()
            ->map(fn (ProductCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'children' => $this->categoryTreeChildren($categories, $category->id),
            ])
            ->toArray();
    }

    /**
     * The catalog-visibility values a catalog page may show.
     *
     * A shopper who typed something is looking for a name, so the search-only
     * products join the listing ones. Browse it any other way — the catalog
     * page, a category, a link, an empty search box — and they stay out. The
     * wider set is earned by the term, never by the URL, so a search-only
     * product cannot be browsed by category or paged through.
     *
     * @return list<string>
     */
    protected function visibilitiesFor(Request $request): array
    {
        return $request->filled('search')
            ? Product::SEARCH_VISIBILITIES
            : Product::LISTING_VISIBILITIES;
    }

    /**
     * @param  list<string>  $visibilities
     */
    protected function baseQuery(array $visibilities = Product::LISTING_VISIBILITIES): EloquentBuilder
    {
        return Product::published()
            ->whereIn('catalog_visibility', $visibilities)
            ->with($this->groupedItems());
    }

    /**
     * Eager-load a product's bundle children for a public payload.
     *
     * The grid, the rails, related products and the wishlist all need the
     * children so a grouped card can show what is in the kit, and all four used
     * to load them unconstrained. A `hidden` child then shipped its name and
     * price inside the parent's JSON - and then 404'd the moment a shopper
     * clicked the link the card had just printed.
     *
     * `reachable()` rather than `inListing()`: a child that is search-only
     * still belongs in a kit a shopper is already looking at, the same
     * argument the direct-product route makes. Only `hidden` comes out.
     */
    protected function groupedItems(): array
    {
        return ['groupedItems' => fn ($q) => $q
            ->select('products.id', 'name', 'slug', 'price', 'thumbnail_url')
            ->reachable()];
    }

    /**
     * Bounds for the catalog price filter.
     *
     * The raw `price` column is 0 on variable and grouped parents, so deriving
     * the bounds from it advertised a catalog starting at 0. Take the floor and
     * ceiling across every price a shopper can actually pay: simple product
     * prices, the cached variation ranges, and the bundled items' own prices.
     *
     * Scoped to the listing set, because these bounds describe the products the
     * page can show. Counting a hidden or search-only product priced at 5000
     * stretched the slider past anything browseable, and narrowing it to a
     * range with no products in it produced an empty catalog page.
     *
     * @return array{min: float, max: float}
     */
    protected function effectivePriceBounds(): array
    {
        $lows = [
            (float) (Product::published()->inListing()->where('price', '>', 0)->min('price') ?? 0),
            (float) (Product::published()->inListing()->whereNotNull('min_variation_price')->min('min_variation_price') ?? 0),
        ];

        $highs = [
            (float) (Product::published()->inListing()->max('price') ?? 0),
            (float) (Product::published()->inListing()->whereNotNull('max_variation_price')->max('max_variation_price') ?? 0),
        ];

        // The cached variation columns are only as fresh as the last sync, so
        // the live active variations are counted too.
        $liveVariations = fn (): QueryBuilder => DB::table('product_variations as live')
            ->whereIn('live.product_id', Product::published()->inListing()->select('products.id'))
            ->where('live.is_active', true)
            ->whereNotNull('live.active_price')
            ->where('live.active_price', '>', 0);

        $lows[] = (float) ($liveVariations()->min('live.active_price') ?? 0);
        $highs[] = (float) ($liveVariations()->max('live.active_price') ?? 0);

        $bundledItems = fn (): QueryBuilder => DB::table('product_grouped_products as links')
            ->join('products as child', 'child.id', '=', 'links.product_id')
            ->whereIn('links.group_product_id', Product::published()->inListing()->select('products.id'))
            // The parent being browsable is not enough: the child is a separate
            // product with its own visibility, and its price is what the shopper
            // would pay. A `hidden` child priced at 5000 stretched the slider
            // past anything actually browseable.
            ->where(function (QueryBuilder $q) {
                $q->whereNull('child.catalog_visibility')
                    ->orWhere('child.catalog_visibility', '!=', Product::HIDDEN);
            })
            ->where('child.price', '>', 0);

        $lows[] = (float) ($bundledItems()->min('child.price') ?? 0);
        $highs[] = (float) ($bundledItems()->max('child.price') ?? 0);

        $lows = array_values(array_filter($lows, fn (float $low): bool => $low > 0));
        $high = max($highs);

        return [
            'min' => $lows === [] ? 0.0 : min($lows),
            'max' => $high > 0 ? $high : 1000.0,
        ];
    }

    protected function applyFilters(EloquentBuilder $query, Request $request): void
    {
        if ($request->filled('search')) {
            $this->productSearch()->applyTo($query, $this->productSearch()->term((string) $request->search), ['name', 'description']);
        }

        if ($request->filled('category')) {
            $query->whereHas('categories', fn ($c) => $c->where('slug', $request->category));
        }

        if ($request->filled('brand')) {
            $query->whereHas('brands', fn ($b) => $b->where('slug', $request->brand));
        }

        if ($request->filled('attribute_terms')) {
            $termIds = array_values(array_filter(array_map('intval', (array) $request->attribute_terms)));
            if ($termIds) {
                $query->whereHas('attributeTerms', fn ($t) => $t->whereIn('attribute_terms.id', $termIds));
            }
        }

        if ($request->filled('min_price') || $request->filled('max_price')) {
            $from = $this->currencyService->storefrontCode();
            $to = $this->currencyService->defaultCode();

            $floor = $request->filled('min_price')
                ? $this->currencyService->convert((float) $request->min_price, $from, $to)
                : null;
            $ceiling = $request->filled('max_price')
                ? $this->currencyService->convert((float) $request->max_price, $from, $to)
                : null;

            $this->applyPriceWindow($query, $floor, $ceiling);
        }
    }

    /**
     * Match products whose payable prices overlap the requested window.
     *
     * Comparing the raw `price` column excluded variable and grouped products
     * outright, because that column is 0 on both: a 300-500 window matched
     * nothing at all, even for a product whose variations run 349-409. A
     * product now matches when any price a shopper can pay for it falls inside
     * the window - its own price, any of its variations, or any bundled item.
     * With only one bound supplied the window is treated as open-ended.
     */
    protected function applyPriceWindow(EloquentBuilder $query, ?float $floor, ?float $ceiling): void
    {
        if ($floor === null && $ceiling === null) {
            return;
        }

        $query->where(function (EloquentBuilder $window) use ($floor, $ceiling) {
            // A simple product has exactly one payable price. `price = 0` is this
            // codebase's "no price of its own" sentinel rather than "free", so
            // it must not satisfy a window that happens to start at 0.
            $window->orWhere(function (EloquentBuilder $simple) use ($floor, $ceiling) {
                $simple->where('price', '>', 0);

                $this->priceWithin($simple, 'price', $floor, $ceiling);
            });

            // A variable product matches when its variation range overlaps.
            $window->orWhere(function (EloquentBuilder $variable) use ($floor, $ceiling) {
                $this->applyVariationWindow($variable, $floor, $ceiling);
            });

            // A grouped product matches when any bundled item is in the window.
            $window->orWhereExists(function (QueryBuilder $sub) use ($floor, $ceiling) {
                $sub->selectRaw('1')
                    ->from('product_grouped_products as links')
                    ->join('products as item', 'item.id', '=', 'links.product_id')
                    ->whereColumn('links.group_product_id', 'products.id')
                    ->where('item.price', '>', 0);

                $this->priceWithin($sub, 'item.price', $floor, $ceiling);
            });
        });
    }

    /**
     * Constrain a variable product by its variations.
     *
     * The cached `min/max_variation_price` columns are the fast path, but they
     * are only resynced by the variation model events and the scheduled-sales
     * command, so a bulk update that bypasses them can leave them missing or
     * stale - and a product with no trustworthy cache would otherwise be
     * dropped from every price filter. The live branch is the safety net.
     */
    protected function applyVariationWindow(EloquentBuilder $query, ?float $floor, ?float $ceiling): void
    {
        $query->where(function (EloquentBuilder $variations) use ($floor, $ceiling) {
            $variations->where('min_variation_price', '>', 0)
                ->whereNotNull('max_variation_price');

            $this->rangeOverlaps(
                $variations,
                'min_variation_price',
                'max_variation_price',
                $floor,
                $ceiling
            );

            $variations->orWhereExists(function (QueryBuilder $sub) use ($floor, $ceiling) {
                $sub->selectRaw('1')
                    ->from('product_variations as live')
                    ->whereColumn('live.product_id', 'products.id')
                    ->where('live.is_active', true)
                    ->whereNotNull('live.active_price')
                    ->where('live.active_price', '>', 0);

                $this->priceWithin($sub, 'live.active_price', $floor, $ceiling);
            });
        });
    }

    /** Constrain a single price to fall inside the window. */
    protected function priceWithin(QueryBuilder|EloquentBuilder $query, string $column, ?float $floor, ?float $ceiling): void
    {
        if ($floor !== null) {
            $query->where($column, '>=', $floor);
        }

        if ($ceiling !== null) {
            $query->where($column, '<=', $ceiling);
        }
    }

    /**
     * Constrain a price *range* to overlap the window: the range's floor has to
     * be at or below the ceiling, and its ceiling at or above the floor. An
     * absent window bound is unconstrained.
     */
    protected function rangeOverlaps(EloquentBuilder $query, string $minColumn, string $maxColumn, ?float $floor, ?float $ceiling): void
    {
        if ($ceiling !== null) {
            $query->where($minColumn, '<=', $ceiling);
        }

        if ($floor !== null) {
            $query->where($maxColumn, '>=', $floor);
        }
    }

    protected function renderCatalog(Request $request, EloquentBuilder $query, array $options = [])
    {
        $sortField = match ($request->sort) {
            'price_asc' => 'price',
            'price_desc' => 'price',
            'name' => 'name',
            default => 'created_at',
        };
        $sortDir = match ($request->sort) {
            'price_desc' => 'desc',
            'price_asc' => 'asc',
            'name' => 'asc',
            default => 'desc',
        };

        $products = $query->orderBy($sortField, $sortDir)->paginate(12)->withQueryString();

        $currency = $this->currencyService->storefrontCode();
        $default = $this->currencyService->defaultCode();

        foreach ($products as $product) {
            $this->priceResolver->hydrate($product, $currency, $this->priceGroupId());
        }

        // A category link is a promise that the page has something in it, and
        // the sidebar is a browse surface, so both the link and the count are
        // scoped to the listing set. `whereHas('products')` with no constraint
        // listed a category whose entire catalog was withdrawn, and the count
        // beside it included every `search`-only product the page can never
        // show - leaking both the existence of withdrawn stock and a count
        // that does not match the grid underneath it.
        //
        // The result is a tree (PR 11D), not a flat list: a category that can
        // list something is nested under its parent, and a child whose parent
        // cannot is offered at the top level.
        $categories = $this->categoryTree(
            ProductCategory::select('id', 'name', 'slug', 'parent_id')
                ->whereHas('products', fn ($q) => $q->published()->inListing())
                ->orderBy('name')
                ->get()
        );

        $brands = ProductBrand::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->published()->inListing()])
            ->orderBy('name')
            ->get()
            ->filter(fn ($b) => $b->products_count > 0)
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'products_count' => $b->products_count,
            ])
            ->values();

        // An attribute facet has to be scoped at both levels. The attribute
        // itself, or a brand with only withdrawn products, still turned up as a
        // filter. And every term of a surviving attribute used to be offered,
        // including swatch names and swatch images that only a `hidden` product
        // uses - a way to read a withdrawn product's options.
        $attributeFilters = Attribute::where('is_active', true)
            ->whereHas('terms')
            ->whereHas('products', fn ($q) => $q->published()->inListing())
            ->with(['terms' => fn ($q) => $q
                ->whereHas('products', fn ($products) => $products->published()->inListing())
                ->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'display_type' => $a->display_type,
                'terms' => $a->terms->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'swatch_value' => $t->swatch_value,
                ])->values(),
            ])
            ->values();

        $bounds = $this->effectivePriceBounds();

        $priceRange = [
            $bounds['min'],
            $bounds['max'],
        ];

        if ($currency !== $default) {
            $priceRange = [
                $this->currencyService->convert($priceRange[0], $default, $currency),
                $this->currencyService->convert($priceRange[1], $default, $currency),
            ];
        }

        $data = [
            // `through()` rewrites each row but keeps the paginator's link
            // metadata, so the grid's page controls survive the trim.
            'products' => $products->through(
                fn (Product $product) => StorefrontProductResource::make($product)->resolve($request)
            ),
            'categories' => $categories,
            'brands' => $brands,
            'attributeFilters' => $attributeFilters,
            'filters' => $request->only(['search', 'category', 'brand', 'attribute_terms', 'min_price', 'max_price', 'sort']),
            'priceRange' => $priceRange,
        ];

        if (! empty($options['activeCategory'])) {
            $data['activeCategory'] = $options['activeCategory'];
        }

        if (! empty($options['breadcrumbs'])) {
            $data['breadcrumbs'] = $options['breadcrumbs'];
        }

        return Inertia::render('shop/storefront/productgrid', $data);
    }

    protected function homepageData(Request $request): array
    {
        $featured = Product::published()
            ->inListing()
            ->featured()
            ->latest()
            ->take(8)
            ->get();

        $newArrivals = Product::published()
            ->inListing()
            ->latest()
            ->take(8)
            ->get();

        $currency = $this->currencyService->storefrontCode();

        foreach ($featured as $product) {
            $this->priceResolver->hydrate($product, $currency, $this->priceGroupId());
        }

        foreach ($newArrivals as $product) {
            $this->priceResolver->hydrate($product, $currency, $this->priceGroupId());
        }

        // `inListing()` for the same reason as the catalog sidebar above: the
        // tile links to a listing page, so it counts what that page can show.
        $categoryGrid = ProductCategory::withCount(['products' => fn ($q) => $q->published()->inListing()])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn ($c) => $c->products_count > 0)
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'products_count' => $c->products_count,
            ])
            ->values();

        return [
            'hero' => config('shop.storefront.hero'),
            'featured' => StorefrontProductResource::collection($featured)->resolve($request),
            'newArrivals' => StorefrontProductResource::collection($newArrivals)->resolve($request),
            'categories' => $categoryGrid,
        ];
    }

    protected function relatedProducts(Product $product)
    {
        $withBundles = fn ($q) => $q->with($this->groupedItems());

        // Merchant-curated links first (PR 11B). The cross-sells and up-sells
        // the admin picked keep their `sort_order`, provided they are still
        // published and browseable - a link to a withdrawn product would 404
        // the rail, which is worse than not linking it at all.
        $curated = $withBundles(
            $product->curatedRelations()
                ->published()
                ->inListing()
                ->where('products.id', '!=', $product->id)
        )->get()->unique('id')->values();

        if ($curated->count() >= 4) {
            return $curated->take(4);
        }

        $categoryIds = $product->categories()->pluck('product_categories.id');

        $categoryBased = $withBundles(Product::published()->inListing())
            ->where('id', '!=', $product->id)
            ->whereNotIn('id', $curated->pluck('id'))
            ->when($categoryIds->isNotEmpty(), fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereIn('product_categories.id', $categoryIds)))
            ->inRandomOrder()
            ->limit(4 - $curated->count())
            ->get();

        return $curated->concat($categoryBased);
    }
}
