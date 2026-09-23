<?php

namespace Nreljed\ShopModule\Payments;

use Nreljed\ShopModule\Contracts\PaymentDriver;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Services\StripeService;

/**
 * Stripe gateway driver.
 *
 * Wraps the low-level `StripeService` and exposes only the normalized driver
 * surface, keeping controllers/webhooks free of the Stripe SDK types.
 */
final class StripePaymentDriver implements PaymentDriver
{
    public function __construct(
        protected StripeService $stripeService,
    ) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function isAvailable(): bool
    {
        return $this->stripeService->getPublishableKey() !== '';
    }

    public function getPublishableKey(): ?string
    {
        return $this->stripeService->getPublishableKey();
    }

    public function createPaymentIntent(Order $order, string $currency = 'eur'): PaymentIntentResult
    {
        $intent = $this->stripeService->createPaymentIntent($order, $currency);

        return new PaymentIntentResult(
            id: $intent->id,
            clientSecret: $intent->client_secret,
        );
    }

    public function refund(Order $order, ?float $amount = null): void
    {
        $this->stripeService->refund($order, $amount);
    }

    public function verifyWebhook(string $payload, string $signature): array
    {
        return $this->stripeService->verifyWebhook($payload, $signature);
    }
}
