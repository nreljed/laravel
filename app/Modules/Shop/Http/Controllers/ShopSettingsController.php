<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class ShopSettingsController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'default_product_status' => 'nullable|string|in:draft,published',
            'low_stock_threshold' => 'nullable|integer|min:0|max:999',
            'hold_stock_enabled' => 'nullable|boolean',
            'hold_stock_minutes' => 'nullable|integer|min:1|max:10080',
            'backorder_default' => 'nullable|string|in:do_not_allow,allow_notify,allow',
            'inventory_enabled' => 'nullable|boolean',
            'show_out_of_stock' => 'nullable|boolean',
            'currency' => 'nullable|string|max:5',
            'currency_symbol' => 'nullable|string|max:10',
            'currency_position' => 'nullable|string|in:before,after',
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
        ]);

        $formatted = array_map(fn ($value) => is_bool($value) ? ($value ? '1' : '0') : $value, $validated);

        foreach ($formatted as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'Shop'],
            );
        }

        return redirect()->back()->with('status', 'Shop settings saved.');
    }
}
