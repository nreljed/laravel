<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StorefrontController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::where('status', 'published')
            ->whereIn('catalog_visibility', ['visible', 'catalog']);

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('categories', fn ($c) => $c->where('slug', $request->category));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        $sortField = match ($request->sort) {
            'price_asc' => 'price',
            'price_desc' => 'price',
            'name' => 'name',
            default => 'created_at',
        };
        $sortDir = $request->sort === 'price_desc' ? 'desc' : 'asc';

        $perPage = 12;
        $products = $query->orderBy($sortField, $sortDir)->paginate($perPage)->withQueryString();

        $categories = ProductCategory::select('id', 'name', 'slug')
            ->whereHas('products')
            ->orderBy('name')
            ->get();

        $minPrice = Product::where('status', 'published')->min('price');
        $maxPrice = Product::where('status', 'published')->max('price');

        return Inertia::render('Modules/Shop/Storefront/ProductGrid', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category', 'min_price', 'max_price', 'sort']),
            'priceRange' => [(float) ($minPrice ?? 0), (float) ($maxPrice ?? 1000)],
        ]);
    }

    public function show(Product $product)
    {
        if ($product->status !== 'published') {
            abort(404);
        }

        $product->load([
            'categories',
            'reviews' => fn ($q) => $q->approved()->with('user')->latest()->limit(10),
            'variationAttributes.attribute.terms',
            'productAttributes' => fn ($q) => $q->where('visible', true)->orderBy('position')->with('attribute.terms'),
            'downloads' => fn ($q) => $q->orderBy('sort_order'),
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

        $attributes = [];
        $variations = collect();
        $priceRange = ['min' => (float) $product->price, 'max' => (float) $product->price];
        $defaultVariation = null;

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

            $priceRange = $product->price_range;
            $defaultVariation = $product->getDefaultVariation();
        }

        $related = Product::where('status', 'published')
            ->where('id', '!=', $product->id)
            ->inRandomOrder()
            ->limit(4)
            ->get();

        return Inertia::render('Modules/Shop/Storefront/ProductDetail', [
            'product' => $product,
            'related' => $related,
            'variations' => $variations,
            'attributes' => $attributes,
            'priceRange' => $priceRange,
            'defaultVariation' => $defaultVariation,
            'visibleAttributes' => $visibleAttributes,
        ]);
    }

    public function category(Request $request, string $slug)
    {
        $category = ProductCategory::where('slug', $slug)->firstOrFail();

        $query = Product::where('status', 'published')
            ->whereHas('categories', fn ($c) => $c->where('slug', $slug));

        $perPage = 12;
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        $categories = ProductCategory::select('id', 'name', 'slug')
            ->whereHas('products')
            ->orderBy('name')
            ->get();

        return Inertia::render('Modules/Shop/Storefront/ProductGrid', [
            'products' => $products,
            'categories' => $categories,
            'filters' => ['category' => $slug, 'search' => null, 'min_price' => null, 'max_price' => null, 'sort' => null],
            'priceRange' => [0, 1000],
            'activeCategory' => $category->only('id', 'name', 'slug'),
        ]);
    }
}
