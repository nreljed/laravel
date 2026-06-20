<?php

namespace App\Modules\ECommerce\Http\Controllers;

use App\Modules\ECommerce\Models\Product;
use App\Modules\ECommerce\Models\ProductCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('categories');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('sku', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->whereHas('categories', fn($q) => $q->where('product_categories.id', $request->category_id));
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return Inertia::render('ECommerce/Products/Index', [
            'products' => $products,
            'categories' => ProductCategory::active()->get(),
            'filters' => $request->only(['search', 'status', 'category_id']),
        ]);
    }

    public function create()
    {
        return Inertia::render('ECommerce/Products/Form', [
            'product' => null,
            'categories' => ProductCategory::active()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'track_stock' => 'nullable|boolean',
            'allow_backorders' => 'nullable|boolean',
            'status' => 'nullable|string|in:draft,published,archived',
            'type' => 'nullable|string|in:simple,variable,digital,service',
            'is_featured' => 'nullable|boolean',
            'is_taxable' => 'nullable|boolean',
            'tax_class' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:product_categories,id',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);

        $product = Product::create($validated);

        if (!empty($validated['categories'])) {
            $product->categories()->sync($validated['categories']);
        }

        return redirect()->route('dashboard.ecommerce.products.index')
            ->with('status', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $product->load('categories');

        return Inertia::render('ECommerce/Products/Form', [
            'product' => $product,
            'categories' => ProductCategory::active()->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug,' . $product->id,
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'track_stock' => 'nullable|boolean',
            'allow_backorders' => 'nullable|boolean',
            'status' => 'nullable|string|in:draft,published,archived',
            'type' => 'nullable|string|in:simple,variable,digital,service',
            'is_featured' => 'nullable|boolean',
            'is_taxable' => 'nullable|boolean',
            'tax_class' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:product_categories,id',
        ]);

        $product->update($validated);

        if (isset($validated['categories'])) {
            $product->categories()->sync($validated['categories']);
        }

        return redirect()->route('dashboard.ecommerce.products.index')
            ->with('status', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('dashboard.ecommerce.products.index')
            ->with('status', 'Product deleted.');
    }

    public function uploadImage(Request $request)
    {
        $request->validate(['file' => 'required|image|max:5120']);

        $path = $request->file('file')->store('products', 'public');

        return response()->json([
            'url' => Storage::url($path),
            'path' => $path,
        ]);
    }

    public function deleteImage(Request $request)
    {
        $request->validate(['path' => 'required|string']);

        Storage::disk('public')->delete($request->path);

        return response()->json(['success' => true]);
    }
}
