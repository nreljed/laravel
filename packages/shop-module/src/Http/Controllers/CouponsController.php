<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Requests\StoreCouponRequest;
use Nreljed\ShopModule\Resources\CouponResource;

class CouponsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', Coupon::class);

        $coupons = Coupon::withCount('usages')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Coupon $coupon): array => array_merge($coupon->toArray(), [
                'usages_count' => $coupon->usages_count,
            ]))
            ->all();

        return $renderer->renderIndex(CouponResource::KEY, $coupons);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', Coupon::class);

        return $renderer->renderForm(
            CouponResource::KEY,
            null,
            'POST',
            route('dashboard.shop.coupons.store'),
        );
    }

    public function store(StoreCouponRequest $request)
    {
        $this->authorize('create', Coupon::class);

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_automatic'] = $request->boolean('is_automatic', false);
        $validated['exclude_sale_items'] = $request->boolean('exclude_sale_items', false);
        if ($validated['type'] === 'free_shipping') {
            $validated['value'] = 0;
        }
        $validated['created_by'] = $request->user()->id;
        $validated['code'] = strtoupper($validated['code']);

        $coupon = Coupon::create($validated);

        if (! empty($validated['product_ids'])) {
            $coupon->products()->sync($validated['product_ids']);
        }

        return redirect()->route('dashboard.shop.coupons.index')
            ->with('status', 'Coupon created successfully.');
    }

    public function edit(ResourceRenderer $renderer, Coupon $record)
    {
        $this->authorize('view', $record);

        return $renderer->renderForm(
            CouponResource::KEY,
            $record->toArray(),
            'PUT',
            route('dashboard.shop.coupons.update', $record),
        );
    }

    public function update(StoreCouponRequest $request, Coupon $record)
    {
        $this->authorize('update', $record);

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_automatic'] = $request->boolean('is_automatic', false);
        $validated['exclude_sale_items'] = $request->boolean('exclude_sale_items', false);
        if ($validated['type'] === 'free_shipping') {
            $validated['value'] = 0;
        }
        $validated['code'] = strtoupper($validated['code']);

        $record->update($validated);

        if (array_key_exists('product_ids', $validated)) {
            $record->products()->sync($validated['product_ids'] ?? []);
        }

        return redirect()->route('dashboard.shop.coupons.index')
            ->with('status', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Coupon deleted successfully.');
    }

    public function toggleActive(Coupon $coupon)
    {
        $this->authorize('update', $coupon);

        $coupon->update(['is_active' => ! $coupon->is_active]);

        return redirect()->back()->with('status', $coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated.');
    }
}
