<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\ProductField;
use Nreljed\ShopModule\Requests\StoreProductFieldRequest;

class ProductFieldsController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', ProductField::class);

        $productFields = ProductField::query()
            ->withCount('values')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('shop/productfields/index', [
            'productFields' => $productFields,
        ]);
    }

    public function store(StoreProductFieldRequest $request)
    {
        $this->authorize('create', ProductField::class);

        $data = $this->fieldPayload($request);
        $data['sort_order'] = (int) ProductField::max('sort_order') + 1;

        ProductField::create($data);

        return redirect()->back()->with('status', 'Product field created.');
    }

    public function update(StoreProductFieldRequest $request, ProductField $productField)
    {
        $this->authorize('update', $productField);

        $productField->update($this->fieldPayload($request));

        return redirect()->back()->with('status', 'Product field updated.');
    }

    public function destroy(ProductField $productField)
    {
        $this->authorize('delete', $productField);

        $productField->delete();

        return redirect()->back()->with('status', 'Product field deleted.');
    }

    public function reorder(Request $request)
    {
        $this->authorize('create', ProductField::class);

        $request->validate([
            'product_fields' => 'required|array',
            'product_fields.*.id' => 'required|exists:product_fields,id',
            'product_fields.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->product_fields as $productField) {
            ProductField::where('id', $productField['id'])->update([
                'sort_order' => $productField['sort_order'],
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    private function fieldPayload(StoreProductFieldRequest $request): array
    {
        $data = $request->validated();

        $data['options'] = $data['type'] === 'select'
            ? collect($data['options'] ?? [])
                ->filter(fn ($option) => trim((string) ($option['value'] ?? '')) !== '')
                ->values()
                ->all()
            : null;

        $data['is_required'] = (bool) ($data['is_required'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['show_on_storefront'] = (bool) ($data['show_on_storefront'] ?? false);

        if ($data['type'] === 'boolean') {
            $data['default_value'] = isset($data['default_value']) && $data['default_value'] !== ''
                ? filter_var($data['default_value'], FILTER_VALIDATE_BOOLEAN)
                : null;
        } elseif (! isset($data['default_value']) || $data['default_value'] === '') {
            $data['default_value'] = null;
        }

        return $data;
    }
}
