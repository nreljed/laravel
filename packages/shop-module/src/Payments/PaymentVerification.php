<?php

namespace Nreljed\ShopModule\Payments;

/**
 * Normalized outcome of a server-side payment check.
 *
 * The storefront reports *which* payment method a shopper chose, and the
 * browser reports *which* intent it obtained. Neither is evidence that money
 * moved. This carries the gateway's own answer so the order's payment state
 * comes from the gateway rather than from the request.
 */
final class PaymentVerification
{
    public const PAID = 'paid';

    public const PENDING = 'pending';

    public const FAILED = 'failed';

    /**
     * @param  self::PAID|self::PENDING|self::FAILED  $state
     */
    private function __construct(
        public readonly string $state,
        public readonly ?string $reason = null,
    ) {}

    /** The gateway confirms the money is captured. */
    public static function paid(): self
    {
        return new self(self::PAID);
    }

    /**
     * Accepted but not settled yet - asynchronous methods (SEPA, transfers,
     * delayed card authorizations) legitimately sit here. The signed webhook
     * is what eventually moves the order to paid, so the order must be created
     * as pending rather than rejected.
     */
    public static function pending(?string $reason = null): self
    {
        return new self(self::PENDING, $reason);
    }

    /** The payment did not happen, or did not happen for this order. */
    public static function failed(string $reason): self
    {
        return new self(self::FAILED, $reason);
    }

    public function isPaid(): bool
    {
        return $this->state === self::PAID;
    }

    public function isPending(): bool
    {
        return $this->state === self::PENDING;
    }

    public function isFailed(): bool
    {
        return $this->state === self::FAILED;
    }
}
