<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\ProductCategory;
use App\Modules\Shop\Requests\StoreCategoryRequest;
use Inertia\Inertia;

class CategoriesController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::with('children')->orderBy('sort_order')->get();

        return Inertia::render('Modules/Shop/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $this->authorize('create', Product::class);

        $category = ProductCategory::create($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['id' => $category->id, 'name' => $category->name]);
        }

        return redirect()->back()->with('status', 'Category created.');
    }

    public function update(StoreCategoryRequest $request, ProductCategory $category)
    {
        $this->authorize('create', Product::class);

        $category->update($request->validated());

        return redirect()->back()->with('status', 'Category updated.');
    }

    public function destroy(ProductCategory $category)
    {
        $this->authorize('create', Product::class);

        $category->delete();

        return redirect()->back()->with('status', 'Category deleted.');
    }
}
