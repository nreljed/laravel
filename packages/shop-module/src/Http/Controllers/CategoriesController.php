<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Requests\StoreCategoryRequest;
use Nreljed\ShopModule\Resources\CategoryResource;

class CategoriesController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        // The route group's `shop.manage` is the outer boundary; every action
        // here authorises individually so a permission split later does not
        // silently expose the list. The other three use `create` on Product
        // because a category is part of a product, so reading the taxonomy is
        // the same gate as writing it.
        $this->authorize('create', Product::class);

        $categories = ProductCategory::withCount('products')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ProductCategory $category): array => $category->toArray())
            ->all();

        return $renderer->renderIndex(CategoryResource::KEY, $categories);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', Product::class);

        return $renderer->renderForm(
            CategoryResource::KEY,
            null,
            'POST',
            route('dashboard.shop.categories.store'),
        );
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

    public function edit(ResourceRenderer $renderer, ProductCategory $record)
    {
        $this->authorize('create', Product::class);

        return $renderer->renderForm(
            CategoryResource::KEY,
            $record->toArray(),
            'PUT',
            route('dashboard.shop.categories.update', $record),
        );
    }

    public function update(StoreCategoryRequest $request, ProductCategory $record)
    {
        $this->authorize('create', Product::class);

        $record->update($request->validated());

        return redirect()->back()->with('status', 'Category updated.');
    }

    public function destroy(ProductCategory $record)
    {
        $this->authorize('create', Product::class);

        $record->delete();

        return redirect()->back()->with('status', 'Category deleted.');
    }
}