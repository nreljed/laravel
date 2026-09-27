<?php

namespace Nreljed\ShopModule\Contracts;

use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Payments\PaymentIntentResult;
use Nreljed\ShopModule\Payments\PaymentVerification;

/**
 * Payment gateway abstraction.
 *
 * Concrete drivers wrap a gateway (Stripe) or a no-op catch-all (manual).
 * The checkout flow asks the registry (`PaymentManager`) for a driver by the
 * storefront's payment-method key and never touches gateway SDK objects
 * directly.
 */
interface PaymentDriver
{
    public function name(): string;

    public function isAvailable(): bool;

    public function getPublishableKey(): ?string;

    /**
     * Create an external payment intent (e.g. a Stripe PaymentIntent) for an
     * order amount previously captured at quote time.
     *
     * @throws \LogicException when the driver does not support intents (manual).
     */
    public function createPaymentIntent(Order $order, string $currency = 'eur'): PaymentIntentResult;

    /**
     * Ask the gateway whether `$paymentReference` actually paid `$order`.
     *
     * Checkout cannot take the browser's word for it: the client chooses
     * `payment_method` and reports the intent id it was handed, so both are
     * attacker-controlled. Every driver must therefore implement a real
     * server-side check, and must return `PaymentVerification::failed()`
     * rather than `paid()` whenever it cannot positively confirm the payment.
     *
     * Callers use `isPaid()` to flip the order, `isPending()` for
     * asynchronous methods the signed webhook will settle, and `isFailed()` to
     * refuse the checkout outright.
     */
    public function verifyPayment(string $paymentReference, Order $order): PaymentVerification;

    /**
     * @return array<string, mixed>
     */
    public function verifyWebhook(string $payload, string $signature): array;

    /**
     * Issue a full or partial refund against the order's original transaction.
     */
    public function refund(Order $order, ?float $amount = null): void;
}