<?php

namespace Nreljed\ShopModule\Pipelines\Checkout;

use Closure;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Pipelines\CheckoutContext;

class CreateOrder
{
    public function handle(CheckoutContext $context, Closure $next)
    {
        $quote = $context->quote;
        $validated = $context->validated;
        $user = $context->user;

        $appliedCouponsSnapshot = collect($quote['coupons'])->map(fn ($coupon) => [
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
        ])->toArray();

        $attributes = [
            'user_id' => $user?->id,
            'customer_email' => $user?->email ?? $validated['customer_email'],
            'customer_name' => $user?->name ?? $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'] ?? $validated['billing_address']['phone'] ?? null,
            'subtotal' => $quote['subtotal'],
            'discount_total' => $quote['discount_total'],
            'shipping_total' => $quote['shipping_total'],
            'tax_total' => $quote['tax']['tax_total'],
            'total' => $quote['total'],
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
        ];

        if ($context->provisionalOrder) {
            $context->provisionalOrder->update($attributes);
            $context->order = $context->provisionalOrder;
        } else {
            $context->order = Order::create($attributes);
        }

        return $next($context);
    }
}