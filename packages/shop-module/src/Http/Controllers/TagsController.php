<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductTag;

class TagsController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Product::class);

        $tags = ProductTag::withCount('products')->orderBy('name')->get();

        return Inertia::render('shop/tags/index', [
            'tags' => $tags,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:product_tags,name',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $tag = ProductTag::create($validated);

        if ($request->wantsJson()) {
            return response()->json(['id' => $tag->id, 'name' => $tag->name]);
        }

        return redirect()->back()->with('status', 'Tag created.');
    }

    public function update(Request $request, ProductTag $tag): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:product_tags,name,'.$tag->id,
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $tag->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['id' => $tag->id, 'name' => $tag->name]);
        }

        return redirect()->back()->with('status', 'Tag updated.');
    }

    public function destroy(Request $request, ProductTag $tag): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $tag->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('status', 'Tag deleted.');
    }
}
