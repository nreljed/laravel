<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Requests\StoreCategoryRequest;

class CategoriesController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::with('children')->orderBy('sort_order')->get();

        return Inertia::render('shop/categories/index', [
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
