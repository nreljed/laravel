<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\CartItem;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;
use Nreljed\ShopModule\Pipelines\CartContext;
use Nreljed\ShopModule\Services\CouponService;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\InventoryService;
use Nreljed\ShopModule\Services\QuoteService;

class CartController extends Controller
{
    public function __construct(
        protected CouponService $couponService,
        protected InventoryService $inventoryService,
        protected CurrencyService $currencyService,
        protected QuoteService $quoteService,
    ) {}

    public function index(Request $request)
    {
        $cart = $this->getCart($request);
        $cart->load('items.product', 'items.variation');

        return response()->json($cart->load('items.product', 'items.variation'));
    }

    public function page(Request $request)
    {
        $cart = $this->getCart($request);
        $cart->load('items.product', 'items.variation');

        $autoDiscounts = $cart->items->isNotEmpty()
            ? $this->couponService->getAutoDiscounts($cart->subtotal)
            : collect();

        $appliedCodes = collect($cart->applied_coupons ?? [])->pluck('code')->filter()->values()->all();

        // Single source of truth for the displayed totals — matches checkout.
        $quote = $this->quoteService->quote(
            $cart,
            null,
            null,
            null,
            $request->user()?->id,
        );

        $cart->discount = $quote['discount_total'];
        $cart->currency = $quote['currency'];

        $default = $this->currencyService->defaultCode();
        $display = $this->currencyService->storefrontCode();

        if ($display !== $default) {
            foreach ($cart->items as $item) {
                $item->unit_price = $this->currencyService->convert((float) $item->unit_price, $default, $display);
            }
            $cart->discount = $this->currencyService->convert((float) $quote['discount_total'], $default, $display);
            $cart->currency = $display;
        }

        return Inertia::render('shop/storefront/shoppingcart', [
            'cart' => $cart,
            'quote' => $quote,
            'autoDiscounts' => $autoDiscounts->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'type' => $c->type,
                'label' => $c->auto_discount_label ?? match ($c->type) {
                    'free_shipping' => 'Free Shipping',
                    'percentage' => "{$c->value}% off",
                    'percentage_product' => "{$c->value}% off selected products",
                    'fixed' => $this->formatAmount((float) $c->value).' off',
                    'fixed_product' => $this->formatAmount((float) $c->value).' off selected products',
                    'buy_x_get_y' => 'Buy '.($c->buy_qty ?: 2).' get '.($c->free_qty ?: 1).' free',
                    'tiered' => 'Quantity discounts',
                    default => $c->code,
                },
                'is_applied' => in_array($c->code, $appliedCodes),
            ])->values()->all(),
        ]);
    }

    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:product_variations,id',
            'variant_label' => 'nullable|string|max:255',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        $variation = null;
        if (! empty($validated['variant_id'])) {
            $variation = ProductVariation::findOrFail($validated['variant_id']);
        }

        $cart = $this->getCart($request);

        $existingItem = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variation_id', $validated['variant_id'] ?? null)
            ->first();

        $context = (new Pipeline(app()))
            ->send(new CartContext(
                cart: $cart,
                product: $product,
                variation: $variation,
                quantity: $validated['quantity'],
                existingItem: $existingItem,
                variantLabel: $validated['variant_label'] ?? null,
                totalQuantity: $validated['quantity'] + ($existingItem?->quantity ?? 0),
            ))
            ->through(config('shop.pipelines.cart'))
            ->thenReturn();

        if ($context->error !== null) {
            return response()->json(['error' => $context->error], 422);
        }

        $this->touchLastActivity($cart);

        return response()->json($cart->fresh()->load('items.product', 'items.variation'));
    }

    public function updateItem(Request $request, int $cartItem)
    {
        $cartItem = $this->findOwnCartItem($request, $cartItem);

        $validated = $request->validate(['quantity' => 'required|integer|min:0']);

        if ($validated['quantity'] <= 0) {
            $cartItem->delete();
        } else {
            $product = $cartItem->product;
            $variation = $cartItem->variation;

            // An update that keeps the line in the cart is a purchase, so it
            // carries the same availability rule the add path does. Dropping the
            // line to zero above stays open - removing a withdrawn product is
            // the one thing a shopper must always be able to do.
            if ($product && ! $product->isPurchasable()) {
                return response()->json(['error' => 'This product is no longer available.'], 422);
            }

            if ($product && $product->sold_individually && $validated['quantity'] > 1) {
                return response()->json(['error' => 'This product can only be purchased one at a time.'], 422);
            }

            if ($product && ! $this->inventoryService->validateStock($product, $validated['quantity'], $variation)) {
                return response()->json(['error' => 'Requested quantity exceeds available stock.'], 422);
            }

            $cartItem->update(['quantity' => $validated['quantity']]);
        }

        $this->touchLastActivity($cartItem->cart);

        return response()->json($cartItem->cart->fresh()->load('items.product', 'items.variation'));
    }

    public function removeItem(Request $request, int $cartItem)
    {
        $cartItem = $this->findOwnCartItem($request, $cartItem);

        $cart = $cartItem->cart;
        $cartItem->delete();

        $this->touchLastActivity($cart);

        return response()->json($cart->fresh()->load('items.product', 'items.variation'));
    }

    public function clear(Request $request)
    {
        $cart = $this->getCart($request);
        $cart->items()->delete();

        $this->touchLastActivity($cart);

        return response()->json(['success' => true]);
    }

    public function applyCoupon(Request $request)
    {
        $request->validate(['code' => 'required|string|max:50']);

        $cart = $this->getCart($request);
        $cart->load('items.product', 'items.variation');

        if ($cart->items->isEmpty()) {
            return response()->json(['error' => 'Cart is empty.'], 422);
        }

        $appliedCoupons = $cart->applied_coupons ?? [];
        $existingCoupons = Coupon::whereIn('id', collect($appliedCoupons)->pluck('coupon_id'))->get();

        try {
            $coupon = $this->couponService->validateAndApply(
                $request->code,
                $cart->subtotal,
                $request->user()?->id,
            );

            $this->couponService->validateStack($coupon, $existingCoupons->all(), $cart->subtotal);
        } catch (ValidationException $e) {
            return response()->json($e->errors(), 422);
        }

        $allCoupons = $existingCoupons->push($coupon);
        $stackResult = $this->couponService->calculateStackedDiscount($allCoupons, $cart->subtotal, null, $cart->items);

        $appliedCoupons[] = [
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'discount' => $stackResult['breakdown'][array_key_last($stackResult['breakdown'])]['discount'] ?? 0,
        ];

        $cart->update([
            'coupon_code' => $appliedCoupons[0]['code'] ?? $coupon->code,
            'discount' => $stackResult['total_discount'],
            'applied_coupons' => $appliedCoupons,
        ]);

        $this->touchLastActivity($cart);

        return response()->json($cart->fresh()->load('items.product', 'items.variation'));
    }

    public function removeCoupon(Request $request)
    {
        $request->validate(['code' => 'nullable|string|max:50']);

        $cart = $this->getCart($request);
        $couponCode = $request->code;

        $appliedCoupons = $cart->applied_coupons ?? [];

        if ($couponCode && ! empty($appliedCoupons)) {
            $appliedCoupons = array_values(array_filter($appliedCoupons, fn ($ac) => ($ac['code'] ?? null) !== $couponCode));
        } else {
            $appliedCoupons = [];
        }

        if (empty($appliedCoupons)) {
            $cart->update([
                'coupon_code' => null,
                'discount' => 0,
                'applied_coupons' => null,
            ]);
        } else {
            $couponModels = Coupon::whereIn('id', collect($appliedCoupons)->pluck('coupon_id'))->get();
            $stackResult = $this->couponService->calculateStackedDiscount($couponModels, $cart->subtotal, null, $cart->items);

            $cart->update([
                'coupon_code' => $appliedCoupons[0]['code'],
                'discount' => $stackResult['total_discount'],
                'applied_coupons' => $appliedCoupons,
            ]);
        }

        $this->touchLastActivity($cart);

        return response()->json($cart->fresh()->load('items.product', 'items.variation'));
    }

    /**
     * Resolve a cart item strictly within the caller's own cart.
     *
     * Route-model binding resolved `CartItem` globally, so any shopper could
     * PUT or DELETE another shopper's line by guessing its id, and the JSON
     * response returned that stranger's cart contents and coupons alongside
     * it. Scoping the lookup to the caller's cart closes both; a line outside
     * it is a 404 rather than a 403, since its existence is itself private.
     */
    protected function findOwnCartItem(Request $request, int $cartItemId): CartItem
    {
        $item = $this->getCart($request)->items()->whereKey($cartItemId)->first();

        abort_if($item === null, 404);

        return $item;
    }

    protected function getCart(Request $request): Cart
    {
        if ($user = $request->user()) {
            return Cart::firstOrCreate(['user_id' => $user->id], ['currency' => $this->currencyService->defaultCode()]);
        }

        $sessionId = $request->session()->getId();

        return Cart::firstOrCreate(['session_id' => $sessionId], ['currency' => $this->currencyService->defaultCode()]);
    }

    protected function formatAmount(float $amount): string
    {
        return $this->currencyService->format($amount, $this->currencyService->defaultCode());
    }

    protected function touchLastActivity(Cart $cart): void
    {
        $cart->update(['last_activity_at' => now()]);
    }
}
