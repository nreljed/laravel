<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Attribute;
use Nreljed\ShopModule\Models\AttributeTerm;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Requests\StoreAttributeRequest;
use Nreljed\ShopModule\Requests\StoreAttributeTermRequest;

class AttributesController extends Controller
{
    public function index()
    {
        $this->authorize('create', Product::class);

        $attributes = Attribute::with('terms')->orderBy('sort_order')->get();

        return Inertia::render('shop/attributes/index', [
            'attributes' => $attributes,
        ]);
    }

    public function store(StoreAttributeRequest $request)
    {
        $this->authorize('create', Product::class);

        Attribute::create($request->validated());

        return redirect()->back()->with('status', 'Attribute created.');
    }

    public function update(StoreAttributeRequest $request, Attribute $attribute)
    {
        $this->authorize('create', Product::class);

        $attribute->update($request->validated());

        return redirect()->back()->with('status', 'Attribute updated.');
    }

    public function destroy(Attribute $attribute)
    {
        $this->authorize('create', Product::class);

        $attribute->delete();

        return redirect()->back()->with('status', 'Attribute deleted.');
    }

    public function storeTerm(StoreAttributeTermRequest $request, Attribute $attribute)
    {
        $this->authorize('create', Product::class);

        $attribute->terms()->create($request->validated());

        return redirect()->back()->with('status', 'Term created.');
    }

    public function updateTerm(StoreAttributeTermRequest $request, Attribute $attribute, AttributeTerm $term)
    {
        $this->authorize('create', Product::class);

        $term->update($request->validated());

        return redirect()->back()->with('status', 'Term updated.');
    }

    public function destroyTerm(Attribute $attribute, AttributeTerm $term)
    {
        $this->authorize('create', Product::class);

        $term->delete();

        return redirect()->back()->with('status', 'Term deleted.');
    }

    public function listTerms(Attribute $attribute)
    {
        // This one is a JSON endpoint the variation and filter builders call as
        // soon as the attribute screen mounts, so it was reachable by anything
        // that cleared the route group's `shop.manage` gate without a check of
        // its own. It reads the taxonomy, so it uses the same `create`-on-Product
        // gate as every other action in this controller.
        $this->authorize('create', Product::class);

        return response()->json($attribute->terms()->orderBy('sort_order')->get());
    }

    public function reorderTerms(Request $request, Attribute $attribute)
    {
        $this->authorize('create', Product::class);
        $request->validate([
            'terms' => 'required|array',
            'terms.*.id' => 'required|exists:attribute_terms,id',
            'terms.*.sort_order' => 'required|integer|min:0',
        ]);
        foreach ($request->terms as $term) {
            AttributeTerm::where('id', $term['id'])->update(['sort_order' => $term['sort_order']]);
        }

        return response()->json(['status' => 'ok']);
    }

    public function reorder(Request $request)
    {
        $this->authorize('create', Product::class);
        $request->validate([
            'attributes' => 'required|array',
            'attributes.*.id' => 'required|exists:attributes,id',
            'attributes.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->attributes as $item) {
            Attribute::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['status' => 'ok']);
    }
}
