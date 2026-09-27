<?php

namespace Nreljed\ShopModule\Payments;

use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Services\StripeService;

/**
 * Decides whether a Stripe intent actually settles a given order.
 *
 * Both places that can move an order to paid - the synchronous check after
 * checkout and the signed webhook - ask this one object, rather than each
 * re-implementing the rules. The two paths receive the intent in different
 * shapes (a `PaymentIntent` from a retrieve call, a plain array from an event
 * payload) and the security rules are subtle enough that duplicating them is
 * how they drift out of step and leave one path unguarded.
 *
 * The rules, in order:
 *
 *  1. The intent must be the one the order references, otherwise a paid intent
 *     belonging to another order could settle this one.
 *  2. It must carry the order stamp written when the intent was raised.
 *     `CreateOrder` re-prices a reused draft from the live cart, so an intent
 *     somebody else paid, for the same amount, must not settle a basket that
 *     was never charged for.
 *  3. It must be quoted for the order's own total, in the order's own currency.
 *  4. `processing` is pending, not paid: asynchronous methods legitimately sit
 *     there and the webhook settles them once funds clear.
 */
final class PaymentIntentMatcher
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    /**
     * @param  string  $status  The intent's Stripe status, e.g. `succeeded`.
     * @param  int|null  $amount  The amount the intent was raised for, in minor units.
     * @param  int|null  $amountReceived  What has actually landed, in minor units.
     * @param  string|null  $metadataOrderId  The order the intent was stamped with.
     */
    public function match(
        Order $order,
        string $intentId,
        ?string $status,
        ?int $amount,
        ?int $amountReceived,
        ?string $currency,
        ?string $metadataOrderId,
    ): PaymentVerification {
        if ($intentId === '' || $intentId !== (string) $order->payment_intent_id) {
            return PaymentVerification::failed('The payment reference does not match this order.');
        }

        if ($metadataOrderId === null || $metadataOrderId !== (string) $order->id) {
            return PaymentVerification::failed('The payment was taken for a different order.');
        }

        $expected = $this->expectedCents($order);

        if ($amount === null || $amount !== $expected) {
            return PaymentVerification::failed('The amount paid does not match the order total.');
        }

        if ($currency !== null && strtolower($currency) !== $this->orderCurrency($order)) {
            return PaymentVerification::failed('The payment was taken in a different currency.');
        }

        return match ($status) {
            'succeeded' => $amountReceived === $expected
                ? PaymentVerification::paid()
                // A partial capture is not payment in full.
                : PaymentVerification::failed('Only part of the order total was paid.'),
            'processing' => PaymentVerification::pending('The payment is still processing.'),
            'canceled' => PaymentVerification::failed('The payment was cancelled.'),
            default => PaymentVerification::failed('The payment has not completed.'),
        };
    }

    private function expectedCents(Order $order): int
    {
        return $this->stripeService->toCents((float) $order->total, $this->orderCurrency($order));
    }

    private function orderCurrency(Order $order): string
    {
        return strtolower((string) ($order->currency ?: 'eur'));
    }
}
