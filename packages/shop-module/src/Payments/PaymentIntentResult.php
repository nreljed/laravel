<?php

namespace Nreljed\ShopModule\Payments;

/**
 * Normalized shape returned by a PaymentDriver when a new intent is created,
 * so callers never depend on a specific gateway's SDK objects.
 */
final class PaymentIntentResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $clientSecret,
    ) {}
}