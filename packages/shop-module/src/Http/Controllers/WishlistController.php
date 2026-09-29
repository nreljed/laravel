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

        // A saved list, not a browse: a search-only product stays, because the
        // customer chose it, but a hidden one goes. Leaving it here would show
        // a card linking to a page that 404s, which reads as a broken shop
        // rather than as an editor's decision.
        $products = Product::whereIn('id', $productIds)
            ->published()
            ->reachable()
            ->with(['categories', 'groupedItems' => fn ($q) => $q
                ->select('products.id', 'name', 'slug', 'price', 'thumbnail_url')
                ->reachable()])
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
        // The list page above drops `hidden` products, so accepting one here
        // left a row nothing would ever render: the product was confirmed to
        // exist, added invisibly, and its id kept coming back in the shared
        // `wishlistIds` prop. `reachable()` is the same rule the list uses.
        abort_unless($product->isVisibleOnStorefront(), 404);

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
