<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\ShippingMethod;
use App\Modules\Shop\Requests\StoreShippingMethodRequest;
use Inertia\Inertia;

class ShippingMethodsController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', ShippingMethod::class);

        $methods = ShippingMethod::orderBy('sort_order')->paginate(20);

        return Inertia::render('Modules/Shop/ShippingMethods/Index', [
            'methods' => $methods,
        ]);
    }

    public function store(StoreShippingMethodRequest $request)
    {
        $this->authorize('create', ShippingMethod::class);

        ShippingMethod::create($request->validated());

        return redirect()->back()->with('status', 'Shipping method created.');
    }

    public function update(StoreShippingMethodRequest $request, ShippingMethod $shippingMethod)
    {
        $this->authorize('update', $shippingMethod);

        $shippingMethod->update($request->validated());

        return redirect()->back()->with('status', 'Shipping method updated.');
    }

    public function destroy(ShippingMethod $shippingMethod)
    {
        $this->authorize('delete', $shippingMethod);

        $shippingMethod->delete();

        return redirect()->back()->with('status', 'Shipping method deleted.');
    }

    public function toggleActive(ShippingMethod $shippingMethod)
    {
        $this->authorize('update', $shippingMethod);

        $shippingMethod->update(['is_active' => ! $shippingMethod->is_active]);

        return redirect()->back()->with('status', $shippingMethod->is_active ? 'Method activated.' : 'Method deactivated.');
    }
}
