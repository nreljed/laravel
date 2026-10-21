<?php

namespace Nreljed\MarketingModule\Support;

/**
 * A provider error reduced to the three facts the delivery pipeline needs:
 * what happened, whether trying again could work, and how long to wait.
 */
final class NormalizedError
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly bool $retryable = false,
        public readonly bool $rateLimited = false,
        public readonly ?int $retryAfter = null,
    ) {}

    public static function permanent(string $code, string $message): self
    {
        return new self($code, $message, false, false, null);
    }

    public static function transient(string $code, string $message, ?int $retryAfter = null): self
    {
        return new self($code, $message, true, $retryAfter !== null, $retryAfter);
    }
}
