<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Http\Resources\StorefrontProductResource;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\Wishlist;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\CustomerPricingContext;
use Nreljed\ShopModule\Services\PriceResolver;

class WishlistController extends Controller
{
    public function __construct(
        protected CurrencyService $currencyService,
        protected PriceResolver $priceResolver,
        protected CustomerPricingContext $pricingContext,
    ) {}

    public function index(Request $request)
    {
        $productIds = Wishlist::where('user_id', auth()->id())
            ->pluck('product_id');

        $products = Product::whereIn('id', $productIds)
            ->published()
            ->with(['categories', 'groupedItems:id,name,slug,price,thumbnail_url'])
            ->paginate(12)
            ->withQueryString();

        $currency = $this->currencyService->storefrontCode();
        $groupId = $this->pricingContext->groupId();

        foreach ($products as $product) {
            $this->priceResolver->hydrate($product, $currency, $groupId);
        }

        return Inertia::render('shop/wishlist/index', [
            // The wishlist is a storefront page, so it gets the same allowlist
            // as the catalog. A signed-in customer is not a merchant and has no
            // business seeing `cost_price` either.
            'products' => $products->through(
                fn (Product $product) => StorefrontProductResource::make($product)->resolve($request)
            ),
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
