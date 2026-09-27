<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
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

class StorefrontController extends Controller
{
    public function __construct(
        protected CurrencyService $currencyService,
        protected PriceResolver $priceResolver,
        protected CustomerPricingContext $pricingContext,
    ) {}

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
            'groupedItems' => fn ($q) => $q->select('products.id', 'name', 'slug', 'price', 'thumbnail_url'),
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

    public function category(Request $request, string $slug)
    {
        $category = ProductCategory::where('slug', $slug)->firstOrFail();

        $query = $this->baseQuery($this->visibilitiesFor($request))
            ->whereHas('categories', fn ($c) => $c->where('slug', $slug));
        $this->applyFilters($query, $request);

        return $this->renderCatalog($request, $query, [
            'activeCategory' => $category->only('id', 'name', 'slug'),
        ]);
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
            ->with('groupedItems:id,name,slug,price,thumbnail_url');
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
            $q = $request->search;
            $query->where(function (EloquentBuilder $w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
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

        $categories = ProductCategory::select('id', 'name', 'slug')
            ->whereHas('products')
            ->orderBy('name')
            ->get();

        $brands = ProductBrand::where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->published()])
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

        $attributeFilters = Attribute::where('is_active', true)
            ->whereHas('terms')
            ->whereHas('products', fn ($q) => $q->published())
            ->with('terms')
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

        $categoryGrid = ProductCategory::withCount(['products' => fn ($q) => $q->published()])
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
        $categoryIds = $product->categories()->pluck('product_categories.id');

        $withBundles = fn ($q) => $q->with('groupedItems:id,name,slug,price,thumbnail_url');

        $related = $withBundles(Product::published()->inListing())
            ->where('id', '!=', $product->id)
            ->when($categoryIds->isNotEmpty(), fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereIn('product_categories.id', $categoryIds)))
            ->inRandomOrder()
            ->limit(4)
            ->get();

        if ($related->count() < 4) {
            $extra = $withBundles(Product::published()->inListing())
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->inRandomOrder()
                ->limit(4 - $related->count())
                ->get();
            $related = $related->concat($extra);
        }

        return $related;
    }
}
