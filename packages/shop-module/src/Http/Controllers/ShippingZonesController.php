<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;
use Nreljed\ShopModule\Models\ShippingZone;
use Nreljed\ShopModule\Requests\StoreShippingZoneRequest;
use Nreljed\ShopModule\Resources\ShippingZoneResource;

class ShippingZonesController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', ShippingZone::class);

        $zones = ShippingZone::withCount('methods')
            ->orderBy('name')
            ->get()
            ->map(fn (ShippingZone $zone): array => array_merge($zone->toArray(), [
                'countries_count' => count($zone->countries),
                'methods_count' => $zone->methods_count,
            ]))
            ->all();

        return $renderer->renderIndex(ShippingZoneResource::KEY, $zones);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', ShippingZone::class);

        return $renderer->renderForm(
            ShippingZoneResource::KEY,
            null,
            'POST',
            route('dashboard.shop.shipping-zones.store'),
        );
    }

    public function store(StoreShippingZoneRequest $request)
    {
        $this->authorize('create', ShippingZone::class);

        $validated = $request->validated();

        $zone = ShippingZone::create([
            'name' => $validated['name'],
            'countries' => $validated['countries'] ?? [],
            'regions' => [],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $zone->methods()->sync($this->syncFromRows($validated['methods'] ?? []));

        return redirect()->route('dashboard.shop.shipping-zones.index')
            ->with('status', 'Shipping zone created.');
    }

    public function edit(ResourceRenderer $renderer, ShippingZone $record)
    {
        $this->authorize('view', $record);

        return $renderer->renderForm(
            ShippingZoneResource::KEY,
            $this->recordArray($record),
            'PUT',
            route('dashboard.shop.shipping-zones.update', $record),
        );
    }

    public function update(StoreShippingZoneRequest $request, ShippingZone $record)
    {
        $this->authorize('update', $record);

        $validated = $request->validated();

        $record->update([
            'name' => $validated['name'],
            'countries' => $validated['countries'] ?? [],
            'is_active' => $request->boolean('is_active', $record->is_active),
        ]);

        if (array_key_exists('methods', $validated)) {
            $record->methods()->sync($this->syncFromRows($validated['methods'] ?? []));
        }

        return redirect()->route('dashboard.shop.shipping-zones.index')
            ->with('status', 'Shipping zone updated.');
    }

    public function destroy(ShippingZone $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Shipping zone deleted.');
    }

    /**
     * The record array the kernel form edits: the zone's own attributes plus
     * the methods pivot rows (id + override price) the nested field keeps.
     */
    protected function recordArray(ShippingZone $record): array
    {
        $record->load('methods');

        return array_merge($record->toArray(), [
            'methods' => $record->methods
                ->map(fn ($method): array => [
                    'id' => $method->id,
                    'override_price' => $method->pivot->override_price,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Flatten nested `methods` rows into the pivot sync array. Rows without a
     * method id are skipped (the picker's "please choose" row).
     *
     * @param  array<int, array{id?: int|string, override_price?: mixed}>  $rows
     * @return array<int, array{override_price: mixed}>
     */
    protected function syncFromRows(array $rows): array
    {
        $sync = [];

        foreach ($rows as $row) {
            if (empty($row['id'])) {
                continue;
            }

            $sync[(int) $row['id']] = [
                'override_price' => ($row['override_price'] ?? null) !== '' ? ($row['override_price'] ?? null) : null,
            ];
        }

        return $sync;
    }
}