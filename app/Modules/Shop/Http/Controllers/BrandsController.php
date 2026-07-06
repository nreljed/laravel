<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\ProductBrand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandsController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Product::class);

        $brands = ProductBrand::withCount('products')->orderBy('name')->get();

        return Inertia::render('Modules/Shop/Brands/Index', [
            'brands' => $brands,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:product_brands,name',
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

    public function update(Request $request, ProductBrand $brand): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:product_brands,name,'.$brand->id,
            'description' => 'nullable|string|max:500',
            'image' => 'nullable|string|max:2048',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $brand->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['id' => $brand->id, 'name' => $brand->name]);
        }

        return redirect()->back()->with('status', 'Brand updated.');
    }

    public function destroy(Request $request, ProductBrand $brand): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $brand->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('status', 'Brand deleted.');
    }
}
