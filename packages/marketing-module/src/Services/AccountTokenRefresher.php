<?php

namespace Nreljed\MarketingModule\Services;

use Carbon\CarbonImmutable;
use Nreljed\MarketingModule\Contracts\SocialChannelAdapter;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Support\TokenSet;

/**
 * Refreshes an expiring social grant and persists the rotated tokens.
 *
 * Publishing and metric sync both need this, so it lives in one place rather
 * than being copied into each pipeline.
 */
class AccountTokenRefresher
{
    public function __construct(protected TokenVault $vault) {}

    /**
     * Refresh the account only when its grant is close to expiring.
     */
    public function refreshIfNeeded(SocialAccount $account, SocialChannelAdapter $adapter): void
    {
        if (! $account->needsRefresh()) {
            return;
        }

        $refreshToken = $account->refreshToken();

        if ($refreshToken === null) {
            return;
        }

        $refreshed = $adapter->refreshTokens(TokenSet::make(
            $account->accessToken(),
            $refreshToken,
            $account->expires_at?->getTimestamp(),
            array_values((array) $account->scopes),
        ));

        if ($refreshed->accessToken === null) {
            return;
        }

        $account->forceFill([
            'access_token_encrypted' => $this->vault->encrypt($refreshed->accessToken),
            'refresh_token_encrypted' => $this->vault->encrypt($refreshed->refreshToken ?? $refreshToken),
            'expires_at' => $refreshed->expiresAt !== null
                ? CarbonImmutable::createFromTimestamp($refreshed->expiresAt)
                : $account->expires_at,
            'last_validated_at' => CarbonImmutable::now(),
        ])->save();
    }
}
