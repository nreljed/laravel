<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\BackInStockSubscription;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\ProductVariation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifyBackInStockController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'product_id' => 'required|exists:'.(new Product)->getTable().',id',
            'variant_id' => 'nullable|exists:'.(new ProductVariation)->getTable().',id',
        ]);

        $exists = BackInStockSubscription::forProduct($validated['product_id'], $validated['variant_id'])
            ->where('email', $validated['email'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You are already subscribed to this product.'], 200);
        }

        BackInStockSubscription::create([
            'email' => $validated['email'],
            'product_id' => $validated['product_id'],
            'product_variation_id' => $validated['variant_id'],
        ]);

        return response()->json(['message' => 'We will notify you when this product is back in stock.'], 201);
    }
}
