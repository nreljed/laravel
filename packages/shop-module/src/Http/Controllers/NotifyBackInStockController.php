<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\BackInStockSubscription;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;

class NotifyBackInStockController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // `product_id` is validated as a bare integer on purpose. The
        // `exists:products,id` it used to carry answered 422 for a product that
        // never existed but 201 for one an editor had deliberately hidden, which
        // turns a public form into a catalogue oracle: walk the ids and the shop
        // tells you what it is hiding. It also stored a subscription and mailed
        // a "back in stock" notice for a product that is not in the shop at all.
        //
        // So the product is resolved through the storefront scopes below and a
        // miss is a 404 - the same answer the product page gives.
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'product_id' => 'required|integer',
            // Deliberately only an integer here - the variation is checked
            // against the product further down, once the product is known. A
            // bare `exists:product_variations,id` accepted any variation in the
            // shop and stored it against whatever product the caller named, and
            // it was an unauthenticated oracle on top of that: 422 for a
            // variation that existed, and nothing at all for one that did not,
            // which walks the ids straight out of every product's variant
            // structure - hidden products' variations included.
            'variant_id' => 'nullable|integer',
        ]);

        $product = Product::query()
            ->published()
            ->reachable()
            ->find($validated['product_id']);

        abort_unless($product !== null, 404);

        $variationId = null;

        if (! empty($validated['variant_id'])) {
            $variation = ProductVariation::query()
                ->where('product_id', $product->id)
                // The same conditions the cart pipeline applies in
                // `ValidateVariant`. Without them a deactivated or unpriced
                // variation was subscribable while an out-of-stock one - the
                // whole point of the form - was not, and the difference told a
                // caller which ids are live variants behind the product.
                ->active()
                ->where('visible', true)
                ->whereNotNull('active_price')
                ->find($validated['variant_id']);

            // 404, not 422: a variation belonging to some other product and a
            // variation that does not exist have to look the same from out here,
            // or the pairing rule just becomes the next oracle. The subscription
            // table has two independent foreign keys and nothing in the schema
            // stops `product_id` and `product_variation_id` disagreeing, so this
            // check is the only thing keeping a row from claiming product A while
            // pointing at product B's stock.
            abort_unless($variation !== null, 404);

            $variationId = $variation->id;
        }

        $exists = BackInStockSubscription::forProduct($product->id, $variationId)
            ->where('email', $validated['email'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You are already subscribed to this product.'], 200);
        }

        BackInStockSubscription::create([
            'email' => $validated['email'],
            'product_id' => $product->id,
            'product_variation_id' => $variationId,
        ]);

        return response()->json(['message' => 'We will notify you when this product is back in stock.'], 201);
    }
}
