<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\Wishlist;

class WishlistController extends Controller
{
    public function index()
    {
        $productIds = Wishlist::where('user_id', auth()->id())
            ->pluck('product_id');

        $products = Product::whereIn('id', $productIds)
            ->where('status', 'published')
            ->with('categories')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('shop/wishlist/index', [
            'products' => $products,
        ]);
    }

    public function toggle(Product $product): JsonResponse
    {
        $exists = Wishlist::where('user_id', auth()->id())
            ->where('product_id', $product->id)
            ->exists();

        if ($exists) {
            Wishlist::where('user_id', auth()->id())
                ->where('product_id', $product->id)
                ->delete();
            $added = false;
        } else {
            Wishlist::create([
                'user_id' => auth()->id(),
                'product_id' => $product->id,
            ]);
            $added = true;
        }

        return response()->json([
            'added' => $added,
            'wishlistIds' => Wishlist::where('user_id', auth()->id())->pluck('product_id'),
        ]);
    }

    public function ids(): JsonResponse
    {
        return response()->json([
            'wishlistIds' => Wishlist::where('user_id', auth()->id())->pluck('product_id'),
        ]);
    }
}
