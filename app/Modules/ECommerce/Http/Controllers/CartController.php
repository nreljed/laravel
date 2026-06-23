<?php

namespace App\Modules\ECommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ECommerce\Models\Cart;
use App\Modules\ECommerce\Models\CartItem;
use App\Modules\ECommerce\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $this->getCart($request);
        $cart->load('items.product');

        return response()->json($cart->load('items.product'));
    }

    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'variant_label' => 'nullable|string|max:255',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        if (! $product->isInStock()) {
            return response()->json(['error' => 'Product is out of stock.'], 422);
        }

        $cart = $this->getCart($request);

        $existingItem = $cart->items()
            ->where('product_id', $product->id)
            ->where('variant_label', $validated['variant_label'])
            ->first();

        if ($existingItem) {
            $existingItem->increment('quantity', $validated['quantity']);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'unit_price' => $product->price,
                'variant_label' => $validated['variant_label'] ?? null,
            ]);
        }

        return response()->json($cart->fresh()->load('items.product'));
    }

    public function updateItem(Request $request, CartItem $cartItem)
    {
        $validated = $request->validate(['quantity' => 'required|integer|min:0']);

        if ($validated['quantity'] <= 0) {
            $cartItem->delete();
        } else {
            $cartItem->update(['quantity' => $validated['quantity']]);
        }

        return response()->json($cartItem->cart->fresh()->load('items.product'));
    }

    public function removeItem(CartItem $cartItem)
    {
        $cart = $cartItem->cart;
        $cartItem->delete();

        return response()->json($cart->fresh()->load('items.product'));
    }

    public function clear(Request $request)
    {
        $cart = $this->getCart($request);
        $cart->items()->delete();

        return response()->json(['success' => true]);
    }

    protected function getCart(Request $request): Cart
    {
        if ($user = $request->user()) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        $sessionId = $request->session()->getId();

        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }
}
