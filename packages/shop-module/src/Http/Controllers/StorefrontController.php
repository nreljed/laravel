<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Attribute;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductBrand;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Models\ProductFieldValue;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\PriceResolver;

class StorefrontController extends Controller
{
    public function __construct(
        protected CurrencyService $currencyService,
        protected PriceResolver $priceResolver,
    ) {}

    public function index()
    {
        return Inertia::render('shop/storefront/home', [
            'homepage' => $this->homepageData(),
        ]);
    }

    public function products(Request $request)
    {
        $query = $this->baseQuery();
        $this->applyFilters($query, $request);

        return $this->renderCatalog($request, $query);
    }

    public function show(Product $product)
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
        $priceRange = ['min' => (float) $product->price, 'max' => (float) $product->price];
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
            $this->priceResolver->hydrate($child, $currency);
        }

        foreach ($variations as $variation) {
            $this->priceResolver->hydrate($variation, $currency);
        }

        $this->priceResolver->hydrate($product, $currency);

        if ($defaultVariation !== null) {
            $this->priceResolver->hydrate($defaultVariation, $currency);
        }

        $related = $this->relatedProducts($product);

        foreach ($related as $relatedProduct) {
            $this->priceResolver->hydrate($relatedProduct, $currency);
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
            $priceRange = ['min' => (float) $product->price, 'max' => (float) $product->price];
        }

        return Inertia::render('shop/storefront/productdetail', [
            'product' => $product,
            'related' => $related,
            'variations' => $variations,
            'attributes' => $attributes,
            'priceRange' => $priceRange,
            'defaultVariation' => $defaultVariation,
            'visibleAttributes' => $visibleAttributes,
            'specifications' => $specifications,
        ]);
    }

    public function category(Request $request, string $slug)
    {
        $category = ProductCategory::where('slug', $slug)->firstOrFail();

        $query = $this->baseQuery()
            ->whereHas('categories', fn ($c) => $c->where('slug', $slug));
        $this->applyFilters($query, $request);

        return $this->renderCatalog($request, $query, [
            'activeCategory' => $category->only('id', 'name', 'slug'),
        ]);
    }

    protected function baseQuery(): Builder
    {
        return Product::published()
            ->whereIn('catalog_visibility', ['visible', 'catalog']);
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function (Builder $w) use ($q) {
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

            if ($request->filled('min_price')) {
                $query->where('price', '>=', $this->currencyService->convert((float) $request->min_price, $from, $to));
            }

            if ($request->filled('max_price')) {
                $query->where('price', '<=', $this->currencyService->convert((float) $request->max_price, $from, $to));
            }
        }
    }

    protected function renderCatalog(Request $request, Builder $query, array $options = [])
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
            $this->priceResolver->hydrate($product, $currency);
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

        $minPrice = Product::published()->min('price');
        $maxPrice = Product::published()->max('price');

        $priceRange = [
            (float) ($minPrice ?? 0),
            (float) ($maxPrice ?? 1000),
        ];

        if ($currency !== $default) {
            $priceRange = [
                $this->currencyService->convert($priceRange[0], $default, $currency),
                $this->currencyService->convert($priceRange[1], $default, $currency),
            ];
        }

        $data = [
            'products' => $products,
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

    protected function homepageData(): array
    {
        $featured = Product::published()
            ->whereIn('catalog_visibility', ['visible', 'catalog'])
            ->featured()
            ->latest()
            ->take(8)
            ->get();

        $newArrivals = Product::published()
            ->whereIn('catalog_visibility', ['visible', 'catalog'])
            ->latest()
            ->take(8)
            ->get();

        $currency = $this->currencyService->storefrontCode();

        foreach ($featured as $product) {
            $this->priceResolver->hydrate($product, $currency);
        }

        foreach ($newArrivals as $product) {
            $this->priceResolver->hydrate($product, $currency);
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
            'featured' => $featured,
            'newArrivals' => $newArrivals,
            'categories' => $categoryGrid,
        ];
    }

    protected function relatedProducts(Product $product)
    {
        $categoryIds = $product->categories()->pluck('product_categories.id');

        $related = Product::published()
            ->where('id', '!=', $product->id)
            ->when($categoryIds->isNotEmpty(), fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereIn('product_categories.id', $categoryIds)))
            ->inRandomOrder()
            ->limit(4)
            ->get();

        if ($related->count() < 4) {
            $extra = Product::published()
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
