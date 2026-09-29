<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductTag;
use Nreljed\ShopModule\Resources\TagResource;

class TagsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', Product::class);

        $tags = ProductTag::withCount('products')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductTag $tag): array => $tag->toArray())
            ->all();

        return $renderer->renderIndex(TagResource::KEY, $tags);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', Product::class);

        return $renderer->renderForm(
            TagResource::KEY,
            null,
            'POST',
            route('dashboard.shop.tags.store'),
        );
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:product_tags,name',
            'slug' => 'nullable|string|max:255',
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

    public function edit(ResourceRenderer $renderer, ProductTag $record)
    {
        $this->authorize('create', Product::class);

        return $renderer->renderForm(
            TagResource::KEY,
            $record->toArray(),
            'PUT',
            route('dashboard.shop.tags.update', $record),
        );
    }

    public function update(Request $request, ProductTag $record): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:product_tags,name,'.$record->id,
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $record->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['id' => $record->id, 'name' => $record->name]);
        }

        return redirect()->back()->with('status', 'Tag updated.');
    }

    public function destroy(Request $request, ProductTag $record): JsonResponse|RedirectResponse
    {
        $this->authorize('create', Product::class);

        $record->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('status', 'Tag deleted.');
    }
}