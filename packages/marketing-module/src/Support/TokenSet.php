<?php

namespace Nreljed\MarketingModule\Support;

/**
 * A set of OAuth tokens as the platform understands them.
 *
 * `raw` carries the provider's untouched response so an adapter can reach a
 * platform-specific field (a Meta page token, a Pinterest scope list) that
 * has no place in the normalised shape. It is never persisted verbatim: the
 * vault stores the normalised fields, and `raw` is scrubbed before any write
 * to a delivery log.
 */
final class TokenSet
{
    /**
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?string $accessToken = null,
        public readonly ?string $refreshToken = null,
        public readonly ?int $expiresAt = null,
        public readonly array $scopes = [],
        public readonly array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function make(
        ?string $accessToken = null,
        ?string $refreshToken = null,
        ?int $expiresAt = null,
        array $scopes = [],
        array $raw = [],
    ): self {
        return new self($accessToken, $refreshToken, $expiresAt, $scopes, $raw);
    }

    public function isExpired(?int $now = null): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt <= ($now ?? time());
    }

    /**
     * Whether the granted scopes cover the ones the platform needs.
     *
     * @param  list<string>  $required
     */
    public function covers(array $required): bool
    {
        if ($required === []) {
            return true;
        }

        $granted = array_map('strtolower', $this->scopes);

        foreach ($required as $scope) {
            if (! in_array(strtolower($scope), $granted, true)) {
                return false;
            }
        }

        return true;
    }
}
