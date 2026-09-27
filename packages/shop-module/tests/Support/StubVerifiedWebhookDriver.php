<?php

namespace Nreljed\ShopModule\Tests\Support;

use Nreljed\ShopModule\Contracts\PaymentDriver;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Payments\PaymentIntentResult;
use Nreljed\ShopModule\Payments\PaymentVerification;

/**
 * A driver whose `verifyWebhook()` hands back a fixed, already-decoded event.
 *
 * Webhook signature verification is HMAC over the raw body, so exercising it
 * needs a real secret and a real signature; what these tests are actually
 * about is what the controller does with a *verified* event. Stubbing the
 * decode keeps them focused on the transition, and the unsigned-rejection
 * test covers the signature step by making this throw.
 */
final class StubVerifiedWebhookDriver implements PaymentDriver
{
    /**
     * @param  array<string, mixed>  $event
     */
    public function __construct(
        private readonly array $event,
    ) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getPublishableKey(): ?string
    {
        return 'pk_test_stub';
    }

    public function createPaymentIntent(Order $order, string $currency = 'eur'): PaymentIntentResult
    {
        return new PaymentIntentResult(id: 'pi_stub', clientSecret: 'pi_stub_secret');
    }

    public function verifyPayment(string $paymentReference, Order $order): PaymentVerification
    {
        return PaymentVerification::paid();
    }

    public function verifyWebhook(string $payload, string $signature): array
    {
        return $this->event;
    }

    public function refund(Order $order, ?float $amount = null): void
    {
        // Nothing to refund against a stub.
    }
}
