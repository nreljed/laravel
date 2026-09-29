<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;
use Nreljed\ShopModule\Models\Attribute;
use Nreljed\ShopModule\Models\AttributeTerm;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Requests\StoreAttributeRequest;
use Nreljed\ShopModule\Requests\StoreAttributeTermRequest;
use Nreljed\ShopModule\Resources\AttributeResource;

class AttributesController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('create', Product::class);

        $attributes = Attribute::withCount('terms')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Attribute $attribute): array => array_merge($attribute->toArray(), [
                'terms_count' => $attribute->terms_count,
            ]))
            ->all();

        return $renderer->renderIndex(AttributeResource::KEY, $attributes);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', Product::class);

        return $renderer->renderForm(
            AttributeResource::KEY,
            null,
            'POST',
            route('dashboard.shop.attributes.store'),
        );
    }

    public function store(StoreAttributeRequest $request)
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();

        $attribute = Attribute::create([
            'name' => $validated['name'],
            'display_type' => $validated['display_type'] ?? 'text',
            'sort_order' => (Attribute::max('sort_order') ?? -1) + 1,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncTerms($attribute, $validated['terms'] ?? []);

        return redirect()->route('dashboard.shop.attributes.index')
            ->with('status', 'Attribute created.');
    }

    public function edit(ResourceRenderer $renderer, Attribute $record)
    {
        $this->authorize('create', Product::class);

        return $renderer->renderForm(
            AttributeResource::KEY,
            array_merge($record->toArray(), [
                'terms' => $record->terms->map(fn (AttributeTerm $term): array => $term->toArray())->all(),
            ]),
            'PUT',
            route('dashboard.shop.attributes.update', $record),
        );
    }

    public function update(StoreAttributeRequest $request, Attribute $record)
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();

        $record->update([
            'name' => $validated['name'],
            'display_type' => $validated['display_type'] ?? $record->display_type,
            'is_active' => $request->boolean('is_active', $record->is_active),
        ]);

        if (array_key_exists('terms', $validated)) {
            $this->syncTerms($record, $validated['terms'] ?? []);
        }

        return redirect()->route('dashboard.shop.attributes.index')
            ->with('status', 'Attribute updated.');
    }

    public function destroy(Attribute $record)
    {
        $this->authorize('create', Product::class);

        $record->delete();

        return redirect()->back()->with('status', 'Attribute deleted.');
    }

    /**
     * Diff the nested `terms` rows against what the attribute persisted:
     * kept rows update (and keep their id), new rows create, and rows that
     * were removed in the editor are deleted. sort_order is the array order,
     * so the drag ordering is the persisted ordering.
     *
     * @param  array<int, array{id?: int|string, name?: string, swatch_value?: string|null}>  $rows
     */
    protected function syncTerms(Attribute $attribute, array $rows): void
    {
        $kept = [];
        $sort = 0;

        foreach ($rows as $row) {
            $data = [
                'name' => $row['name'] ?? '',
                'swatch_value' => ($row['swatch_value'] ?? '') !== '' ? $row['swatch_value'] : null,
                'sort_order' => $sort++,
            ];

            if (! empty($row['id'])) {
                AttributeTerm::where('attribute_id', $attribute->id)
                    ->where('id', $row['id'])
                    ->update($data);
                $kept[] = (int) $row['id'];
            } else {
                $kept[] = $attribute->terms()->create($data)->id;
            }
        }

        $attribute->terms()->whereNotIn('id', $kept)->delete();
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