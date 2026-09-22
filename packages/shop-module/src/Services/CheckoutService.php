<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Nreljed\Platform\Models\User;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\OrderItem;
use Nreljed\ShopModule\Models\ShippingMethod;

class CheckoutService
{
    public function __construct(
        protected CouponService $couponService,
        protected InventoryService $inventoryService,
        protected QuoteService $quoteService,
    ) {}

    public function processCheckout(Cart $cart, array $validated, ?User $user, ?Order $provisionalOrder = null): Order
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

        return DB::transaction(function () use ($cart, $validated, $shippingMethod, $user, $provisionalOrder) {
            $quote = $this->quoteService->quote(
                $cart,
                $shippingMethod,
                $validated['billing_address'],
                $validated['shipping_address'] ?? null,
                $user?->id,
            );

            $subtotal = $quote['subtotal'];
            $discountTotal = $quote['discount_total'];
            $shippingTotal = $quote['shipping_total'];
            $taxResult = $quote['tax'];
            $total = $quote['total'];
            $validCoupons = $quote['coupons'];

            $appliedCouponsSnapshot = $validCoupons->map(fn ($c) => [
                'coupon_id' => $c->id,
                'code' => $c->code,
                'type' => $c->type,
            ])->toArray();

            // Single provisional order (PR 3): the Stripe flow pre-created a
            // draft order at payment-intent time. Finalize THAT order in place
            // instead of leaving a second order behind.
            if ($provisionalOrder) {
                $order = $provisionalOrder;

                $order->update([
                    'user_id' => $user?->id,
                    'customer_email' => $user?->email ?? $validated['customer_email'],
                    'customer_name' => $user?->name ?? $validated['customer_name'],
                    'customer_phone' => $validated['customer_phone'] ?? $validated['billing_address']['phone'] ?? null,
                    'subtotal' => $subtotal,
                    'discount_total' => $discountTotal,
                    'shipping_total' => $shippingTotal,
                    'tax_total' => $taxResult['tax_total'],
                    'total' => $total,
                    'currency' => $quote['currency'],
                    'exchange_rate' => $quote['exchange_rate'],
                    'status' => 'pending',
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => $validated['payment_method'] === 'stripe' ? 'paid' : 'pending',
                    'payment_intent_id' => $validated['stripe_payment_intent_id'] ?? null,
                    'paid_at' => $validated['payment_method'] === 'stripe' ? now() : null,
                    'billing_address' => $validated['billing_address'],
                    'shipping_address' => $validated['shipping_address'],
                    'applied_coupons' => $appliedCouponsSnapshot,
                ]);
            } else {
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
                    'currency' => $quote['currency'],
                    'exchange_rate' => $quote['exchange_rate'],
                    'status' => 'pending',
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => $validated['payment_method'] === 'stripe' ? 'paid' : 'pending',
                    'payment_intent_id' => $validated['stripe_payment_intent_id'] ?? null,
                    'paid_at' => $validated['payment_method'] === 'stripe' ? now() : null,
                    'billing_address' => $validated['billing_address'],
                    'shipping_address' => $validated['shipping_address'],
                    'applied_coupons' => $appliedCouponsSnapshot,
                ]);
            }

            foreach ($validCoupons as $idx => $coupon) {
                $this->couponService->recordUsage(
                    $coupon,
                    $order->id,
                    $user?->id,
                    $quote['discount_breakdown'][$idx]['discount'] ?? 0,
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
                    'currency' => $quote['currency'],
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
                'currency' => $quote['currency'],
                'converted_to_order_id' => $order->id,
            ]);

            return $order;
        });
    }
}
