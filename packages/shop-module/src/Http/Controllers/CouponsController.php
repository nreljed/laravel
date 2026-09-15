<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\CouponUsage;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Requests\StoreCouponRequest;

class CouponsController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Coupon::class);
        $coupons = Coupon::withCount('usages')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('shop/coupons/index', [
            'coupons' => $coupons,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Coupon::class);

        return Inertia::render('shop/coupons/form', [
            'coupon' => null,
            'products' => $this->productOptions(),
        ]);
    }

    public function store(StoreCouponRequest $request)
    {
        $this->authorize('create', Coupon::class);

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_automatic'] = $request->boolean('is_automatic', false);
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

    public function edit(Coupon $coupon)
    {
        $this->authorize('view', $coupon);

        $coupon->load('products:id');
        $coupon->loadCount('usages');

        $totalDiscount = CouponUsage::where('coupon_id', $coupon->id)->sum('discount_amount');
        $lastUsed = CouponUsage::where('coupon_id', $coupon->id)
            ->orderBy('created_at', 'desc')
            ->first();

        return Inertia::render('shop/coupons/form', [
            'coupon' => array_merge($coupon->toArray(), [
                'total_discount_given' => (float) $totalDiscount,
                'last_used_at' => $lastUsed?->created_at,
            ]),
            'products' => $this->productOptions(),
        ]);
    }

    protected function productOptions(): array
    {
        return Product::select('id', 'name')->orderBy('name')->get()->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
        ])->values()->all();
    }

    public function update(StoreCouponRequest $request, Coupon $coupon)
    {
        $this->authorize('update', $coupon);

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_automatic'] = $request->boolean('is_automatic', false);
        if ($validated['type'] === 'free_shipping') {
            $validated['value'] = 0;
        }
        $validated['code'] = strtoupper($validated['code']);

        $coupon->update($validated);

        if (array_key_exists('product_ids', $validated)) {
            $coupon->products()->sync($validated['product_ids'] ?? []);
        }

        return redirect()->route('dashboard.shop.coupons.index')
            ->with('status', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon)
    {
        $this->authorize('delete', $coupon);

        $coupon->delete();

        return redirect()->back()->with('status', 'Coupon deleted successfully.');
    }

    public function toggleActive(Coupon $coupon)
    {
        $this->authorize('update', $coupon);

        $coupon->update(['is_active' => ! $coupon->is_active]);

        return redirect()->back()->with('status', $coupon->is_active ? 'Coupon activated.' : 'Coupon deactivated.');
    }
}
