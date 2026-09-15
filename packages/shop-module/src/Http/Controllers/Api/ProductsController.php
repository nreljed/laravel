<?php

namespace Nreljed\ShopModule\Http\Controllers\Api;

use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Http\Resources\ProductResource;
use Nreljed\ShopModule\Models\Product;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        $perPage = min((int) ($request->per_page ?? 20), 100);
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        return new ProductResource($product);
    }
}
