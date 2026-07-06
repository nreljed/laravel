<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\Attribute;
use App\Modules\Shop\Models\AttributeTerm;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Requests\StoreAttributeRequest;
use App\Modules\Shop\Requests\StoreAttributeTermRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttributesController extends Controller
{
    public function index()
    {
        $attributes = Attribute::with('terms')->orderBy('sort_order')->get();

        return Inertia::render('Modules/Shop/Attributes/Index', [
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
