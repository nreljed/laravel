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
        protected PaymentIntentMatcher $matcher,
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

    public function verifyPayment(string $paymentReference, Order $order): PaymentVerification
    {
        if ($paymentReference === '') {
            return PaymentVerification::failed('No payment reference was provided.');
        }

        try {
            $intent = $this->stripeService->retrievePaymentIntent($paymentReference);
        } catch (\Throwable $e) {
            // A missing or foreign intent must not read as "paid".
            return PaymentVerification::failed('We could not confirm the payment with the payment provider.');
        }

        return $this->matcher->match(
            $order,
            (string) ($intent->id ?? ''),
            isset($intent->status) ? (string) $intent->status : null,
            isset($intent->amount) ? (int) $intent->amount : null,
            isset($intent->amount_received) ? (int) $intent->amount_received : null,
            isset($intent->currency) ? (string) $intent->currency : null,
            $this->metadataValue($intent, 'order_id'),
        );
    }

    /**
     * Read a metadata value without assuming how the SDK surfaces it.
     *
     * `StripeObject` answers to both array and property access, plain objects
     * only to the latter, and a missing `metadata` is null or an empty
     * container depending on the response. Guessing wrong here would either
     * throw on a legitimate payment or silently skip the ownership check, so
     * all three shapes are handled.
     */
    private function metadataValue(object $intent, string $key): ?string
    {
        $metadata = $intent->metadata ?? null;

        if (is_array($metadata)) {
            $value = $metadata[$key] ?? null;
        } elseif (is_object($metadata)) {
            $value = $metadata->{$key} ?? null;
        } else {
            return null;
        }

        return $value === null ? null : (string) $value;
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