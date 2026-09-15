<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\TaxRate;
use Nreljed\ShopModule\Requests\StoreTaxRateRequest;

class TaxRatesController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', TaxRate::class);

        $rates = TaxRate::orderBy('country')->orderBy('name')->paginate(20);

        return Inertia::render('shop/taxrates/index', [
            'rates' => $rates,
        ]);
    }

    public function store(StoreTaxRateRequest $request)
    {
        $this->authorize('create', TaxRate::class);

        TaxRate::create($request->validated());

        return redirect()->back()->with('status', 'Tax rate created.');
    }

    public function update(StoreTaxRateRequest $request, TaxRate $taxRate)
    {
        $this->authorize('update', $taxRate);

        $taxRate->update($request->validated());

        return redirect()->back()->with('status', 'Tax rate updated.');
    }

    public function destroy(TaxRate $taxRate)
    {
        $this->authorize('delete', $taxRate);

        $taxRate->delete();

        return redirect()->back()->with('status', 'Tax rate deleted.');
    }

    public function toggleActive(TaxRate $taxRate)
    {
        $this->authorize('update', $taxRate);

        $taxRate->update(['is_active' => ! $taxRate->is_active]);

        return redirect()->back()->with('status', $taxRate->is_active ? 'Rate activated.' : 'Rate deactivated.');
    }
}
