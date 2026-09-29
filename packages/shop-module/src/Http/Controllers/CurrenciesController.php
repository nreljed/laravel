<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;
use Nreljed\ShopModule\Models\Currency;
use Nreljed\ShopModule\Resources\CurrencyResource;
use Nreljed\ShopModule\Services\CurrencyService;

class CurrenciesController extends Controller
{
    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', Currency::class);

        $currencies = Currency::query()
            ->orderBy('code')
            ->get()
            ->map(fn (Currency $currency): array => $currency->toArray())
            ->all();

        return $renderer->renderIndex(CurrencyResource::KEY, $currencies);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', Currency::class);

        return $renderer->renderForm(
            CurrencyResource::KEY,
            null,
            'POST',
            route('dashboard.shop.currencies.store'),
        );
    }

    public function store(Request $request)
    {
        $this->authorize('create', Currency::class);

        $validated = $request->validate([
            'code' => 'required|string|size:3|unique:currencies,code',
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'decimal_places' => 'required|integer|min:0|max:4',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            Currency::where('is_default', true)->update(['is_default' => false]);
        }

        Currency::create($validated);

        $this->currencyService->clearCache();

        return redirect()->back()->with('status', 'Currency created.');
    }

    public function edit(ResourceRenderer $renderer, Currency $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(
            CurrencyResource::KEY,
            $record->toArray(),
            'PUT',
            route('dashboard.shop.currencies.update', $record),
        );
    }

    public function update(Request $request, Currency $record)
    {
        $this->authorize('update', $record);

        $validated = $request->validate([
            'code' => 'required|string|size:3|unique:currencies,code,'.$record->id,
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'decimal_places' => 'required|integer|min:0|max:4',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            Currency::where('is_default', true)->where('id', '!=', $record->id)->update(['is_default' => false]);
        }

        $record->update($validated);

        $this->currencyService->clearCache();

        return redirect()->back()->with('status', 'Currency updated.');
    }

    public function destroy(Currency $record)
    {
        $this->authorize('delete', $record);

        if ($record->is_default) {
            return redirect()->back()->with('error', 'Cannot delete the default currency.');
        }

        $record->delete();

        $this->currencyService->clearCache();

        return redirect()->back()->with('status', 'Currency deleted.');
    }

    public function toggleActive(Currency $currency)
    {
        $this->authorize('update', $currency);

        if ($currency->is_default && $currency->is_active) {
            return redirect()->back()->with('error', 'Cannot deactivate the default currency.');
        }

        $currency->update(['is_active' => ! $currency->is_active]);

        $this->currencyService->clearCache();

        return redirect()->back()->with('status', $currency->is_active ? 'Currency activated.' : 'Currency deactivated.');
    }
}