<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\Cart;
use App\Modules\Shop\Models\CartItem;
use App\Modules\Shop\Models\Coupon;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\ProductVariation;
use App\Modules\Shop\Services\CouponService;
use App\Modules\Shop\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(
        protected CouponService $couponService,
        protected InventoryService $inventoryService,
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

        return Inertia::render('Modules/Shop/Storefront/ShoppingCart', [
            'cart' => $cart,
            'autoDiscounts' => $autoDiscounts->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'type' => $c->type,
                'label' => $c->auto_discount_label ?? ($c->type === 'free_shipping' ? 'Free Shipping' : "{$c->value}% off"),
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
        $unitPrice = $product->price;

        if (! empty($validated['variant_id'])) {
            $variation = ProductVariation::findOrFail($validated['variant_id']);
            if ($variation->product_id !== $product->id) {
                return response()->json(['error' => 'Variant does not belong to this product.'], 422);
            }
            if (! $variation->isVisible()) {
                return response()->json(['error' => 'This variant is not available for purchase.'], 422);
            }
            if ($variation->usesParentStock()) {
                if (! $product->isInStock()) {
                    return response()->json(['error' => 'This variant is out of stock.'], 422);
                }
            } elseif ($variation->managesOwnStock() && $variation->stock_quantity <= 0 && $variation->backorders === 'no') {
                return response()->json(['error' => 'This variant is out of stock.'], 422);
            }
            $unitPrice = $variation->effective_price ?? $product->price;
        }

        if (! $product->isInStock() && ! $variation) {
            return response()->json(['error' => 'Product is out of stock.'], 422);
        }

        $totalQty = $validated['quantity'];
        $existingItem = null;

        $cart = $this->getCart($request);

        $existingItem = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variation_id', $validated['variant_id'] ?? null)
            ->first();

        if ($existingItem) {
            $totalQty += $existingItem->quantity;
        }

        if ($product->sold_individually && $totalQty > 1) {
            return response()->json(['error' => 'This product can only be purchased one at a time.'], 422);
        }

        if (! $this->inventoryService->validateStock($product, $totalQty, $variation)) {
            return response()->json(['error' => 'Requested quantity exceeds available stock.'], 422);
        }

        if ($existingItem) {
            $existingItem->update(['quantity' => $totalQty]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'product_variation_id' => $validated['variant_id'] ?? null,
                'quantity' => $validated['quantity'],
                'unit_price' => $unitPrice,
                'variant_label' => $validated['variant_label'] ?? null,
            ]);
        }

        $this->touchLastActivity($cart);

        return response()->json($cart->fresh()->load('items.product', 'items.variation'));
    }

    public function updateItem(Request $request, CartItem $cartItem)
    {
        $validated = $request->validate(['quantity' => 'required|integer|min:0']);

        if ($validated['quantity'] <= 0) {
            $cartItem->delete();
        } else {
            $product = $cartItem->product;
            $variation = $cartItem->variation;

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

    public function removeItem(CartItem $cartItem)
    {
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
        $stackResult = $this->couponService->calculateStackedDiscount($allCoupons, $cart->subtotal);

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
            $stackResult = $this->couponService->calculateStackedDiscount($couponModels, $cart->subtotal);

            $cart->update([
                'coupon_code' => $appliedCoupons[0]['code'],
                'discount' => $stackResult['total_discount'],
                'applied_coupons' => $appliedCoupons,
            ]);
        }

        $this->touchLastActivity($cart);

        return response()->json($cart->fresh()->load('items.product', 'items.variation'));
    }

    protected function getCart(Request $request): Cart
    {
        if ($user = $request->user()) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        $sessionId = $request->session()->getId();

        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }

    protected function touchLastActivity(Cart $cart): void
    {
        $cart->update(['last_activity_at' => now()]);
    }
}
