<?php

namespace Nreljed\ShopModule\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nreljed\ShopModule\Models\Cart;

/**
 * A cart, as a shopper is allowed to see it.
 *
 * This started as `CartController::publicCart()` and lived only there, which
 * left the checkout page reading the other half of the same journey: it
 * rendered `'cart' => $cart` with `items.product` loaded, so every line carried
 * a raw `Product` and with it `cost_price` and `price_hash`. The cart page and
 * the cart JSON feed had been trimmed; the page that spends the money had not.
 *
 * Both controllers now build the payload from here, so there is one cart shape
 * and one privacy posture. The two ends of the journey cannot drift again.
 */
class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Cart $cart */
        $cart = $this->resource;

        $cart->load('items.product', 'items.variation');

        return [
            'id' => $cart->id,
            'items' => CartItemResource::collection($cart->items)->resolve($request),
            // Passed through exactly as serialising the model did, casts and
            // accessors included: `discount` is a `decimal:2`, so it has always
            // reached the page as the string "5.00" and `subtotal`/`total` as
            // floats from their accessors. Re-casting here quietly changed the
            // first one to a float, and the only thing that protects a public
            // payload is that it does not move under you.
            'subtotal' => $cart->subtotal,
            'total' => $cart->total,
            'coupon_code' => $cart->coupon_code,
            'discount' => $cart->discount,
            'currency' => $cart->currency,
            'applied_coupons' => $cart->applied_coupons ?? [],
        ];
    }
}
