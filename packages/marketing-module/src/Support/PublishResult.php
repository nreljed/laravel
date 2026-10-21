<?php

namespace Nreljed\MarketingModule\Support;

/**
 * The outcome of one publish attempt against one account.
 *
 * A post targeting five platforms produces five of these. Partial success is
 * a first-class outcome: one platform rejecting a post must not undo the
 * four that went out.
 */
final class PublishResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $successful,
        public readonly ?string $externalPostId = null,
        public readonly ?string $externalUrl = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $retryable = false,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function published(string $externalPostId, ?string $externalUrl = null, array $raw = []): self
    {
        return new self(true, $externalPostId, $externalUrl, null, null, false, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failed(string $code, string $message, bool $retryable = false, array $raw = []): self
    {
        return new self(false, null, null, $code, $message, $retryable, $raw);
    }
}
