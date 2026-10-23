<?php

namespace Nreljed\MarketingModule\Exceptions;

use Nreljed\MarketingModule\Support\NormalizedError;
use RuntimeException;

/**
 * A metric request that failed in a way the sync service must record.
 *
 * Adapters throw this instead of returning an empty array, so "no metrics"
 * and "the request failed" can never be mistaken for each other.
 */
final class MetricFetchException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly bool $retryable = false,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public static function fromNormalized(NormalizedError $error): self
    {
        return new self($error->code, $error->message, $error->retryable, $error->retryAfter);
    }
}
