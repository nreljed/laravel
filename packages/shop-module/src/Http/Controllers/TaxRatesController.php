<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;
use Nreljed\ShopModule\Models\TaxRate;
use Nreljed\ShopModule\Requests\StoreTaxRateRequest;
use Nreljed\ShopModule\Resources\TaxRateResource;

class TaxRatesController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', TaxRate::class);

        $rates = TaxRate::query()
            ->orderBy('country')
            ->orderBy('name')
            ->get()
            ->map(fn (TaxRate $rate): array => $rate->toArray())
            ->all();

        return $renderer->renderIndex(TaxRateResource::KEY, $rates);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', TaxRate::class);

        return $renderer->renderForm(
            TaxRateResource::KEY,
            null,
            'POST',
            route('dashboard.shop.tax-rates.store'),
        );
    }

    public function store(StoreTaxRateRequest $request)
    {
        $this->authorize('create', TaxRate::class);

        TaxRate::create($request->validated());

        return redirect()->back()->with('status', 'Tax rate created.');
    }

    public function edit(ResourceRenderer $renderer, TaxRate $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(
            TaxRateResource::KEY,
            $record->toArray(),
            'PUT',
            route('dashboard.shop.tax-rates.update', $record),
        );
    }

    public function update(StoreTaxRateRequest $request, TaxRate $record)
    {
        $this->authorize('update', $record);

        $record->update($request->validated());

        return redirect()->back()->with('status', 'Tax rate updated.');
    }

    public function destroy(TaxRate $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Tax rate deleted.');
    }

    public function toggleActive(TaxRate $taxRate)
    {
        $this->authorize('update', $taxRate);

        $taxRate->update(['is_active' => ! $taxRate->is_active]);

        return redirect()->back()->with('status', $taxRate->is_active ? 'Rate activated.' : 'Rate deactivated.');
    }
}