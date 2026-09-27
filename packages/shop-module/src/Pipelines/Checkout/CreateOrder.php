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
            // Every order is created unpaid. The browser chooses
            // `payment_method` and reports the intent it was handed, so neither
            // is evidence that money moved - marking stripe orders `paid` here
            // let a shopper check out without paying. Payment state is
            // applied afterwards by CheckoutService from the gateway's own
            // answer (or by the signed webhook for async methods).
            'payment_status' => 'pending',
            'payment_intent_id' => $validated['stripe_payment_intent_id'] ?? null,
            'paid_at' => null,
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

        // The confirmation link is the only thing that lets a shopper without
        // an account see their own order, so every order needs one. Issued
        // here, the single point every order passes through, rather than at
        // each of the several places that link to an order.
        //
        // Generated only when absent: a reused provisional draft keeps the
        // token it already had, so a link the shopper already has open never
        // stops working part-way through checkout.
        if (! $context->order->confirmation_token) {
            $context->order->update([
                'confirmation_token' => Order::newConfirmationToken(),
            ]);
        }

        return $next($context);
    }
}