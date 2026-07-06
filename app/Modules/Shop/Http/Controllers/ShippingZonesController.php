<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\ShippingMethod;
use App\Modules\Shop\Models\ShippingZone;
use App\Modules\Shop\Requests\StoreShippingZoneRequest;
use Inertia\Inertia;

class ShippingZonesController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', ShippingZone::class);

        $zones = ShippingZone::with('methods')->orderBy('name')->paginate(20);

        return Inertia::render('Modules/Shop/ShippingZones/Index', [
            'zones' => $zones,
        ]);
    }

    public function create()
    {
        $this->authorize('create', ShippingZone::class);

        return Inertia::render('Modules/Shop/ShippingZones/Form', [
            'zone' => null,
            'methods' => ShippingMethod::active()->get(),
        ]);
    }

    public function store(StoreShippingZoneRequest $request)
    {
        $this->authorize('create', ShippingZone::class);

        $validated = $request->validated();

        $zone = ShippingZone::create([
            'name' => $validated['name'],
            'countries' => $validated['countries'] ?? [],
            'regions' => $validated['regions'] ?? [],
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (! empty($validated['method_ids'])) {
            $sync = [];
            foreach ($validated['method_ids'] as $m) {
                $sync[$m['id']] = ['override_price' => $m['override_price'] ?? null];
            }
            $zone->methods()->sync($sync);
        }

        return redirect()->route('dashboard.shop.shipping-zones.index')
            ->with('status', 'Shipping zone created.');
    }

    public function edit(ShippingZone $shippingZone)
    {
        $this->authorize('view', $shippingZone);

        $shippingZone->load('methods');

        return Inertia::render('Modules/Shop/ShippingZones/Form', [
            'zone' => $shippingZone,
            'methods' => ShippingMethod::active()->get(),
        ]);
    }

    public function update(StoreShippingZoneRequest $request, ShippingZone $shippingZone)
    {
        $this->authorize('update', $shippingZone);

        $validated = $request->validated();

        $shippingZone->update([
            'name' => $validated['name'],
            'countries' => $validated['countries'] ?? [],
            'regions' => $validated['regions'] ?? [],
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (array_key_exists('method_ids', $validated)) {
            $sync = [];
            foreach ($validated['method_ids'] as $m) {
                $sync[$m['id']] = ['override_price' => $m['override_price'] ?? null];
            }
            $shippingZone->methods()->sync($sync);
        }

        return redirect()->route('dashboard.shop.shipping-zones.index')
            ->with('status', 'Shipping zone updated.');
    }

    public function destroy(ShippingZone $shippingZone)
    {
        $this->authorize('delete', $shippingZone);

        $shippingZone->delete();

        return redirect()->back()->with('status', 'Shipping zone deleted.');
    }
}
