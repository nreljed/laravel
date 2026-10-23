<?php

namespace Nreljed\MarketingModule\Contracts;

use Laravel\Socialite\Contracts\User as SocialiteUser;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Support\NormalizedError;
use Nreljed\MarketingModule\Support\PostContent;
use Nreljed\MarketingModule\Support\PublishResult;
use Nreljed\MarketingModule\Support\SocialCapabilities;
use Nreljed\MarketingModule\Support\TokenSet;
use Throwable;

/**
 * One social platform.
 *
 * The shape mirrors the shop module's PaymentDriver: a small interface the
 * registry resolves by key, so the publishing pipeline never learns a
 * platform's rules and adding a platform never touches the pipeline.
 */
interface SocialChannelAdapter
{
    /**
     * The platform key stored on social_accounts.platform (e.g. "meta").
     */
    public function platform(): string;

    /**
     * Human label for the platform, shown in the composer and account list.
     */
    public function label(): string;

    /**
     * What this platform accepts.
     */
    public function capabilities(): SocialCapabilities;

    /**
     * The OAuth scopes the platform needs to publish on a user's behalf.
     *
     * @return list<string>
     */
    public function requiredScopes(): array;

    /**
     * The Socialite driver name this platform authenticates through.
     */
    public function socialiteDriver(): string;

    /**
     * Normalise the tokens Socialite obtained during the callback.
     */
    public function hydrateTokenSet(SocialiteUser $user): TokenSet;

    /**
     * List the accounts this token may publish to.
     *
     * A platform may expose several publish targets behind one OAuth grant:
     * a Meta login can reach many Pages, a LinkedIn login can reach several
     * organizations, and each of those carries its own access token. The
     * connect flow asks the user which one to keep.
     *
     * @return list<array{account_id: string, account_name: string, username: ?string, access_token: ?string, metadata: array<string, mixed>}>
     */
    public function discoverAccounts(TokenSet $tokens): array;

    /**
     * Exchange a refresh token for a fresh access token.
     */
    public function refreshTokens(TokenSet $tokens): TokenSet;

    /**
     * Publish one post to one account.
     */
    public function publish(PostContent $content, SocialAccount $account): PublishResult;

    /**
     * Fetch engagement metrics for a published post.
     *
     * Throw on transport, authentication, and API errors so the caller can
     * distinguish a failed request from a platform that exposed no metrics.
     * Return an empty array only when the request succeeded but no metrics are
     * available. A reported zero is data and must be returned as zero.
     *
     * @return array<string, int|float>
     */
    public function fetchMetrics(string $externalPostId, SocialAccount $account): array;

    /**
     * Revoke the platform-side grant where the platform supports it.
     */
    public function revoke(SocialAccount $account): void;

    /**
     * Reduce a provider exception to a retry decision.
     */
    public function mapError(Throwable $e): NormalizedError;
}
