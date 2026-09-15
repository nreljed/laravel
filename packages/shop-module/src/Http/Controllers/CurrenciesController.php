<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Currency;
use Nreljed\ShopModule\Services\CurrencyService;

class CurrenciesController extends Controller
{
    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    public function index(CurrencyService $currencyService)
    {
        $this->authorize('viewAny', Currency::class);

        $currencies = Currency::orderBy('code')->paginate(20);

        return Inertia::render('shop/currencies/index', [
            'currencies' => $currencies,
        ]);
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

    public function update(Request $request, Currency $currency)
    {
        $this->authorize('update', $currency);

        $validated = $request->validate([
            'code' => 'required|string|size:3|unique:currencies,code,'.$currency->id,
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'decimal_places' => 'required|integer|min:0|max:4',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            Currency::where('is_default', true)->where('id', '!=', $currency->id)->update(['is_default' => false]);
        }

        $currency->update($validated);

        $this->currencyService->clearCache();

        return redirect()->back()->with('status', 'Currency updated.');
    }

    public function destroy(Currency $currency)
    {
        $this->authorize('delete', $currency);

        if ($currency->is_default) {
            return redirect()->back()->with('error', 'Cannot delete the default currency.');
        }

        $currency->delete();

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
