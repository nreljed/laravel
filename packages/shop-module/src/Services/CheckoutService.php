<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\OrderItem;
use Nreljed\ShopModule\Models\ShippingMethod;

class CheckoutService
{
    public function __construct(
        protected CouponService $couponService,
        protected ShippingService $shippingService,
        protected TaxService $taxService,
        protected InventoryService $inventoryService,
        protected ShopSettingsService $settings,
    ) {}

    public function processCheckout(Cart $cart, array $validated, ?User $user): Order
    {
        $cart->loadMissing('items.product', 'items.variation');

        foreach ($cart->items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            if (! $this->inventoryService->validateStock($product, $item->quantity, $item->variation)) {
                throw ValidationException::withMessages([
                    'stock' => __(':name is out of stock or insufficient quantity.', ['name' => $item->product_name ?? $product->name]),
                ]);
            }
        }

        $shippingMethod = ShippingMethod::findOrFail($validated['shipping_method_id']);

        return DB::transaction(function () use ($cart, $validated, $shippingMethod, $user) {
            $subtotal = $cart->subtotal;
            $discountTotal = (float) $cart->discount;
            $shippingTotal = $shippingMethod->price;

            $freeAbove = $shippingMethod->free_above;
            if ($freeAbove !== null && $subtotal >= (float) $freeAbove) {
                $shippingTotal = 0;
            }

            $validCoupons = new Collection;
            $appliedCoupons = $cart->applied_coupons ?? [];

            if (empty($appliedCoupons) && $cart->coupon_code) {
                $appliedCoupons = [['code' => $cart->coupon_code]];
            }

            foreach ($appliedCoupons as $ac) {
                $code = $ac['code'] ?? null;
                if (! $code) {
                    continue;
                }

                try {
                    $coupon = $this->couponService->validateAndApply($code, $subtotal, $user?->id);
                    $validCoupons->push($coupon);
                } catch (ValidationException) {
                    // Coupon no longer valid at checkout
                }
            }

            // Auto-discounts (is_automatic) apply without a code — merge any
            // active, valid ones that aren't already applied.
            foreach ($this->couponService->getAutoDiscounts($subtotal) as $auto) {
                if ($validCoupons->contains('id', $auto->id)) {
                    continue;
                }
                $validCoupons->push($auto);
            }

            if ($validCoupons->isNotEmpty()) {
                $stackResult = $this->couponService->calculateStackedDiscount(
                    $validCoupons, $subtotal, $shippingTotal, $cart->items
                );
                $discountTotal = $stackResult['total_discount'];

                foreach ($stackResult['breakdown'] as $b) {
                    if ($b['type'] === 'free_shipping') {
                        $shippingTotal = 0;
                    }
                }
            }

            $stackBreakdown = isset($stackResult) ? $stackResult['breakdown'] : [];

            $taxResult = $this->taxService->calculate(
                $subtotal,
                $validated['billing_address'],
                $validated['shipping_address'] ?? null,
                $shippingTotal,
            );

            $total = max(0, $subtotal - $discountTotal + $shippingTotal + $taxResult['tax_total']);

            $appliedCouponsSnapshot = $validCoupons->map(fn ($c) => [
                'coupon_id' => $c->id,
                'code' => $c->code,
                'type' => $c->type,
            ])->toArray();

            $order = Order::create([
                'user_id' => $user?->id,
                'customer_email' => $user?->email ?? $validated['customer_email'],
                'customer_name' => $user?->name ?? $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? $validated['billing_address']['phone'] ?? null,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'shipping_total' => $shippingTotal,
                'tax_total' => $taxResult['tax_total'],
                'total' => $total,
                'status' => 'pending',
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_method'] === 'stripe' ? 'paid' : 'pending',
                'payment_intent_id' => $validated['stripe_payment_intent_id'] ?? null,
                'paid_at' => $validated['payment_method'] === 'stripe' ? now() : null,
                'billing_address' => $validated['billing_address'],
                'shipping_address' => $validated['shipping_address'],
                'applied_coupons' => $appliedCouponsSnapshot,
            ]);

            foreach ($validCoupons as $idx => $coupon) {
                $this->couponService->recordUsage(
                    $coupon,
                    $order->id,
                    $user?->id,
                    $stackBreakdown[$idx]['discount'] ?? 0,
                );
            }

            foreach ($cart->items as $item) {
                $itemSubtotal = $item->unit_price * $item->quantity;
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_variation_id' => $item->product_variation_id,
                    'product_name' => $item->product->name,
                    'product_sku' => $item->variation?->sku ?? $item->product->sku,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $itemSubtotal,
                    'total' => $itemSubtotal,
                    'variant_label' => $item->variant_label,
                    'image_url' => $item->variation?->image_url ?? $item->product->thumbnail_url,
                ]);
            }

            $this->inventoryService->recordSaleForOrder($order, $user?->id);

            $cart->items()->delete();
            $cart->update([
                'coupon_code' => null,
                'discount' => 0,
                'applied_coupons' => null,
                'converted_to_order_id' => $order->id,
            ]);

            return $order;
        });
    }
}
