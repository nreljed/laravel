<?php

namespace Nreljed\ShopModule\Payments;

use LogicException;
use Nreljed\ShopModule\Contracts\PaymentDriver;
use Nreljed\ShopModule\Models\Order;

/**
 * Catch-all driver for the storefront-level "manual" payment method.
 *
 * Manual orders are created directly at `store()` time with a pending
 * payment status — there is no gateway, so intents and webhooks are
 * intentionally unsupported.
 */
final class ManualPaymentDriver implements PaymentDriver
{
    public function name(): string
    {
        return 'manual';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getPublishableKey(): ?string
    {
        return null;
    }

    public function createPaymentIntent(Order $order, string $currency = 'eur'): PaymentIntentResult
    {
        throw new LogicException('The manual payment method does not create payment intents.');
    }

    public function verifyPayment(string $paymentReference, Order $order): PaymentVerification
    {
        // There is no gateway to ask, so nothing can be confirmed here. Manual
        // orders are reconciled by staff (or bank transfer), so they stay
        // pending rather than being reported as paid or refused.
        return PaymentVerification::pending('Manual payments are confirmed by staff.');
    }

    public function verifyWebhook(string $payload, string $signature): array
    {
        throw new LogicException('The manual payment method has no webhooks.');
    }

    public function refund(Order $order, ?float $amount = null): void
    {
        // Nothing to do against a gateway; refunds are recorded locally.
    }
}