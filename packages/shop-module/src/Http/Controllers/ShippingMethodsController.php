<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Requests\StoreShippingMethodRequest;
use Nreljed\ShopModule\Resources\ShippingMethodResource;

class ShippingMethodsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', ShippingMethod::class);

        $methods = ShippingMethod::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ShippingMethod $method): array => $method->toArray())
            ->all();

        return $renderer->renderIndex(ShippingMethodResource::KEY, $methods);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', ShippingMethod::class);

        return $renderer->renderForm(
            ShippingMethodResource::KEY,
            null,
            'POST',
            route('dashboard.shop.shipping-methods.store'),
        );
    }

    public function store(StoreShippingMethodRequest $request)
    {
        $this->authorize('create', ShippingMethod::class);

        ShippingMethod::create($request->validated());

        return redirect()->back()->with('status', 'Shipping method created.');
    }

    public function edit(ResourceRenderer $renderer, ShippingMethod $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(
            ShippingMethodResource::KEY,
            $record->toArray(),
            'PUT',
            route('dashboard.shop.shipping-methods.update', $record),
        );
    }

    public function update(StoreShippingMethodRequest $request, ShippingMethod $record)
    {
        $this->authorize('update', $record);

        $record->update($request->validated());

        return redirect()->back()->with('status', 'Shipping method updated.');
    }

    public function destroy(ShippingMethod $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Shipping method deleted.');
    }

    public function toggleActive(ShippingMethod $shippingMethod)
    {
        $this->authorize('update', $shippingMethod);

        $shippingMethod->update(['is_active' => ! $shippingMethod->is_active]);

        return redirect()->back()->with('status', $shippingMethod->is_active ? 'Method activated.' : 'Method deactivated.');
    }

    /**
     * Options payload for the zone form's lazy method picker. Static options
     * would force live-DB state into the attribute descriptor (FeatureRegistry
     * forbids it), so the nested select fetches the active method catalog.
     */
    public function options(Request $request)
    {
        $this->authorize('viewAny', ShippingMethod::class);

        return response()->json(
            ShippingMethod::active()
                ->orderBy('name')
                ->get()
                ->map(fn (ShippingMethod $method): array => [
                    'value' => $method->id,
                    'label' => $method->name,
                ])
                ->all()
        );
    }
}