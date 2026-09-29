<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductBrand;
use Nreljed\ShopModule\Resources\BrandResource;

class BrandsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', Product::class);

        $brands = ProductBrand::withCount('products')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductBrand $brand): array => $brand->toArray())
            ->all();

        return $renderer->renderIndex(BrandResource::KEY, $brands);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', Product::class);

        return $renderer->renderForm(
            BrandResource::KEY,
            null,
            'POST',
            route('dashboard.shop.brands.store'),
        );
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:product_brands,name',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'image' => 'nullable|string|max:2048',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $brand = ProductBrand::create($validated);

        if ($request->wantsJson()) {
            return response()->json(['id' => $brand->id, 'name' => $brand->name]);
        }

        return redirect()->back()->with('status', 'Brand created.');
    }

    public function edit(ResourceRenderer $renderer, ProductBrand $record)
    {
        $this->authorize('create', Product::class);

        return $renderer->renderForm(
            BrandResource::KEY,
            $record->toArray(),
            'PUT',
            route('dashboard.shop.brands.update', $record),
        );
    }

    public function update(Request $request, ProductBrand $record): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:product_brands,name,'.$record->id,
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'image' => 'nullable|string|max:2048',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $record->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['id' => $record->id, 'name' => $record->name]);
        }

        return redirect()->back()->with('status', 'Brand updated.');
    }

    public function destroy(Request $request, ProductBrand $record): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $record->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('status', 'Brand deleted.');
    }
}