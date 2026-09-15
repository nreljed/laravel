<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\Setting;
use Nreljed\Platform\Services\SettingsService;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\ShopSettingsService;
use Nreljed\ShopModule\Support\Countries;

class ShopSettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('shop/settings/index', [
            'settings' => app(ShopSettingsService::class)->all(),
            'countries' => Countries::all(),
            'currencies' => app(CurrencyService::class)->getActiveCurrencies()
                ->map(fn ($currency) => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [
            'store_name' => 'nullable|string|max:255',
            'store_email' => 'nullable|email|max:255',
            'store_address_line1' => 'nullable|string|max:255',
            'store_address_line2' => 'nullable|string|max:255',
            'store_city' => 'nullable|string|max:255',
            'store_postcode' => 'nullable|string|max:20',
            'base_country' => 'required|string|size:2',
            'selling_locations_mode' => 'required|string|in:all,specific,except',
            'selling_locations' => 'nullable|array',
            'selling_locations.*' => 'string|size:2',
            'default_product_status' => 'nullable|string|in:draft,published',
            'low_stock_threshold' => 'nullable|integer|min:0|max:999',
            'hold_stock_enabled' => 'nullable|boolean',
            'hold_stock_minutes' => 'nullable|integer|min:1|max:10080',
            'backorder_default' => 'nullable|string|in:do_not_allow,allow_notify,allow',
            'inventory_enabled' => 'nullable|boolean',
            'show_out_of_stock' => 'nullable|boolean',
            'currency' => 'nullable|string|max:5',
            'currency_symbol' => 'nullable|string|max:10',
            'currency_position' => 'nullable|string|in:before,after,before_space,after_space',
            'thousands_separator' => 'nullable|string|max:2',
            'decimal_separator' => 'nullable|string|max:2',
            'price_decimals' => 'nullable|integer|min:0|max:4',
            'prices_include_tax' => 'nullable|boolean',
            'tax_based_on' => 'nullable|string|in:billing,shipping',
            'tax_shipping' => 'nullable|boolean',
            'tax_display' => 'nullable|string|in:inc,exc,both',
            'weight_unit' => 'nullable|string|in:kg,g,lb,oz',
            'dimension_unit' => 'nullable|string|in:cm,m,in,ft',
            'enable_reviews' => 'nullable|boolean',
            'enable_wishlist' => 'nullable|boolean',
            'auto_complete_orders' => 'nullable|boolean',
            'abandoned_cart_enabled' => 'nullable|boolean',
            'abandoned_cart_hours_until_abandoned' => 'nullable|integer|min:1|max:168',
            'abandoned_cart_sequence_hours' => 'nullable|string|max:100',
            'sale_scheduling_enabled' => 'nullable|boolean',
            'sale_scheduling_normalize_to_day_boundaries' => 'nullable|boolean',
            'enable_guest_checkout' => 'nullable|boolean',
            'require_company_field' => 'nullable|boolean',
            'require_terms' => 'nullable|boolean',
            'checkout_default_country' => 'nullable|string|size:2',
        ];

        $validated = $request->validate($rules);

        $formatted = array_map(function ($value) {
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return is_array($value) ? json_encode($value) : $value;
        }, $validated);

        foreach ($formatted as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'shop'],
            );
        }

        SettingsService::clearCache();
        app(ShopSettingsService::class)->clearCache();

        return redirect()->back()->with('status', 'Shop settings saved.');
    }
}
