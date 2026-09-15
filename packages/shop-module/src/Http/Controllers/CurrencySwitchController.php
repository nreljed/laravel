<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Services\CurrencyService;

class CurrencySwitchController extends Controller
{
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => 'required|string|size:3',
        ]);

        $currency = app(CurrencyService::class)->getByCode($validated['currency']);

        if ($currency && $currency->is_active) {
            session(['selected_currency' => $currency->code]);
        }

        return redirect()->back();
    }
}
