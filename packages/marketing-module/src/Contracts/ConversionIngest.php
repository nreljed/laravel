<?php

namespace Nreljed\MarketingModule\Contracts;

use Nreljed\MarketingModule\Models\MarketingConversion;

/**
 * The shop reports conversions; the marketing module stores the attribution.
 *
 * The contract is deliberately small. The shop owns orders, payment state and
 * revenue; the module only needs the link or click that brought the visitor,
 * an idempotency key, and the value the shop chooses to report.
 */
interface ConversionIngest
{
    /**
     * @param  array{
     *   link_id?: int|null,
     *   short_code?: string|null,
     *   click_id?: int|null,
     *   source?: string|null,
     *   event_type?: string|null,
     *   external_reference?: string|null,
     *   idempotency_key?: string|null,
     *   value?: int|float|string|null,
     *   currency?: string|null,
     *   occurred_at?: \DateTimeInterface|string|null,
     *   metadata?: array<string, mixed>|null
     * }  $payload
     */
    public function record(array $payload): MarketingConversion;
}
