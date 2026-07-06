<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\Order;
use App\Modules\Shop\Services\StripeService;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected StripeService $stripeService,
    ) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = $this->stripeService->verifyWebhook($payload, $sigHeader);
        } catch (\Exception $e) {
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

        $order->update([
            'payment_status' => 'paid',
            'payment_intent_id' => $intent['id'],
            'paid_at' => now(),
        ]);
    }

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

        $order->update(['payment_status' => 'failed']);
    }

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

        $order->update(['payment_status' => 'refunded']);
    }
}
