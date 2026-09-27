<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Payments\PaymentIntentMatcher;
use Nreljed\ShopModule\Services\PaymentManager;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected PaymentManager $paymentManager,
        protected PaymentIntentMatcher $matcher,
    ) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        // Cast rather than pass through: a request with no signature header
        // would otherwise hand `null` to a `string` parameter and raise a
        // TypeError, which is an `Error` and slips past a `catch (\Exception)`.
        $sigHeader = (string) $request->header('Stripe-Signature', '');

        try {
            $event = $this->paymentManager->driver('stripe')->verifyWebhook($payload, $sigHeader);
        } catch (\Throwable $e) {
            // Anything at all here means the event is untrustworthy, including
            // the fatal errors a malformed payload can provoke. It must be
            // answered with a rejection, never a transition.
            return response('Webhook signature verification failed.', 400);
        }

        $type = $event['type'] ?? '';

        match ($type) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event['data']['object'] ?? []),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event['data']['object'] ?? []),
            'charge.refunded' => $this->handleChargeRefunded($event['data']['object'] ?? []),
            default => null,
        };

        return response('OK', 200);
    }

    /**
     * Settle an asynchronously-paid order.
     *
     * This is the authoritative path for methods that do not settle inline
     * (SEPA, bank transfer), so it applies the same rules as the post-checkout
     * check rather than trusting the payload: the intent must belong to this
     * order, carry its stamp, and be for the order's own total in its own
     * currency. Without that, an intent that succeeded for a different amount
     * would confirm an order that was never paid in full.
     */
    protected function handlePaymentSucceeded(array $intent): void
    {
        $orderId = $intent['metadata']['order_id'] ?? null;
        if (! $orderId) {
            return;
        }

        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        $verification = $this->matcher->match(
            $order,
            (string) ($intent['id'] ?? ''),
            isset($intent['status']) ? (string) $intent['status'] : null,
            isset($intent['amount']) ? (int) $intent['amount'] : null,
            isset($intent['amount_received']) ? (int) $intent['amount_received'] : null,
            isset($intent['currency']) ? (string) $intent['currency'] : null,
            isset($intent['metadata']['order_id']) ? (string) $intent['metadata']['order_id'] : null,
        );

        if (! $verification->isPaid()) {
            // Deliberately silent: a mismatch is either a Stripe quirk or an
            // attempt to settle an order that was not paid, and answering
            // either way tells the sender what they got away with. The order
            // simply stays pending for staff to look at.
            return;
        }

        // Stripe retries events, so the same success arrives more than once.
        // Re-stamping `paid_at` on every retry would rewrite the record of when
        // the shopper actually paid.
        if ($order->payment_status === 'paid' && $order->paid_at !== null) {
            return;
        }

        $order->update([
            'payment_status' => 'paid',
            'payment_intent_id' => $intent['id'],
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark a genuinely failed payment as failed.
     *
     * Delivery is not ordered, so a late `payment_failed` can arrive after the
     * success that settled the order - or after a refund. Either would be a
     * downgrade of a settled state, which is why those are left alone.
     */
    protected function handlePaymentFailed(array $intent): void
    {
        $orderId = $intent['metadata']['order_id'] ?? null;
        if (! $orderId) {
            return;
        }

        $order = Order::find($orderId);
        if (! $order) {
            return;
        }

        if (in_array($order->payment_status, ['paid', 'refunded', 'partially_refunded'], true)) {
            return;
        }

        $order->update(['payment_status' => 'failed']);
    }

    /**
     * Record a refund.
     *
     * Stripe emits this for partial refunds too, so the amount returned is
     * compared against the amount charged: refunding €5 of a €115 order has not
     * refunded the order, and marking it `refunded` would tell staff the money
     * is back when it is not.
     */
    protected function handleChargeRefunded(array $charge): void
    {
        $paymentIntentId = $charge['payment_intent'] ?? null;
        if (! $paymentIntentId) {
            return;
        }

        $order = Order::where('payment_intent_id', $paymentIntentId)->first();
        if (! $order) {
            return;
        }

        $refunded = isset($charge['amount_refunded']) ? (int) $charge['amount_refunded'] : null;
        $charged = isset($charge['amount']) ? (int) $charge['amount'] : null;

        $status = $refunded !== null && $charged !== null && $refunded < $charged
            ? 'partially_refunded'
            : 'refunded';

        if ($order->payment_status === $status) {
            return;
        }

        $order->update(['payment_status' => $status]);
    }
}
