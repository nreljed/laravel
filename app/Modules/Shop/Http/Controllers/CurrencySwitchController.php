<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Services\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
