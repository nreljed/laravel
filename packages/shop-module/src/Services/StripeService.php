<?php

namespace Nreljed\ShopModule\Services;

use Nreljed\ShopModule\Models\Order;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeService
{
    protected string $secretKey;

    protected string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret', env('STRIPE_SECRET', ''));
        $this->webhookSecret = config('services.stripe.webhook_secret', env('STRIPE_WEBHOOK_SECRET', ''));

        Stripe::setApiKey($this->secretKey);
    }

    public function getPublishableKey(): string
    {
        return config('services.stripe.key', env('STRIPE_KEY', ''));
    }

    public function createPaymentIntent(Order $order, string $currency = 'usd'): PaymentIntent
    {
        return PaymentIntent::create([
            'amount' => $this->toCents($order->total, $currency),
            'currency' => strtolower($currency),
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ]);
    }

    public function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return PaymentIntent::retrieve($paymentIntentId);
    }

    public function confirmPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return PaymentIntent::update($paymentIntentId, [
            'metadata' => ['confirmed' => 'true'],
        ]);
    }

    public function refund(Order $order, ?float $amount = null): Refund
    {
        $params = [
            'payment_intent' => $order->payment_intent_id,
        ];

        if ($amount !== null) {
            $params['amount'] = $this->toCents($amount, $order->currency ?? 'usd');
        }

        return Refund::create($params);
    }

    public function verifyWebhook(string $payload, string $sigHeader): array
    {
        $event = Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);

        return $event->toArray();
    }

    protected function toCents(float $amount, string $currency = 'usd'): int
    {
        $zeroDecimal = ['jpy', 'krw', 'vnd', 'clp', 'xof', 'xaf'];

        if (in_array(strtolower($currency), $zeroDecimal)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }
}
