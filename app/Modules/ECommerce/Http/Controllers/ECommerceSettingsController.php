<?php

namespace App\Modules\ECommerce\Http\Controllers;

use App\Services\SettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ECommerceSettingsController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'default_product_status' => 'nullable|string|in:draft,published',
            'low_stock_threshold' => 'nullable|integer|min:0|max:999',
            'show_out_of_stock' => 'nullable|boolean',
            'currency' => 'nullable|string|max:5',
            'currency_position' => 'nullable|string|in:before,after',
            'weight_unit' => 'nullable|string|in:kg,g,lb,oz',
            'dimension_unit' => 'nullable|string|in:cm,m,in,ft',
            'enable_reviews' => 'nullable|boolean',
            'enable_wishlist' => 'nullable|boolean',
            'auto_complete_orders' => 'nullable|boolean',
        ]);

        SettingsService::setMany($validated);

        return redirect()->back()->with('status', 'E-Commerce settings saved.');
    }
}
