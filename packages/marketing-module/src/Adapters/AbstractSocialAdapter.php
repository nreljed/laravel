<?php

namespace Nreljed\MarketingModule\Adapters;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Nreljed\MarketingModule\Contracts\SocialChannelAdapter;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Support\NormalizedError;
use Nreljed\MarketingModule\Support\PostContent;
use Nreljed\MarketingModule\Support\PublishResult;
use Nreljed\MarketingModule\Support\SocialCapabilities;
use Nreljed\MarketingModule\Support\TokenSet;
use Throwable;

/**
 * Shared plumbing for every social platform adapter.
 *
 * Everything that is the same on all five platforms lives here: token
 * normalisation, the HTTP client, and the error-to-retry mapping. A concrete
 * adapter only declares what is actually platform-specific.
 */
abstract class AbstractSocialAdapter implements SocialChannelAdapter
{
    /**
     * @param  array<string, mixed>  $config  The platform's entry from config('marketing.oauth.apps').
     */
    public function __construct(protected array $config = []) {}

    public function label(): string
    {
        return ucfirst($this->platform());
    }

    public function requiredScopes(): array
    {
        return array_values(array_filter(
            (array) Arr::get($this->config, 'scopes', []),
            static fn ($scope): bool => is_string($scope) && $scope !== '',
        ));
    }

    public function socialiteDriver(): string
    {
        return (string) Arr::get($this->config, 'driver', $this->platform());
    }

    public function hydrateTokenSet(SocialiteUser $user): TokenSet
    {
        return TokenSet::make(
            $user->token,
            $user->refreshToken ?? null,
            $user->expiresIn ? time() + (int) $user->expiresIn : null,
            $this->grantedScopes($user),
        );
    }

    public function refreshTokens(TokenSet $tokens): TokenSet
    {
        if ($tokens->refreshToken === null) {
            return $tokens;
        }

        $response = $this->post($this->tokenUrl(), [
            'grant_type' => 'refresh_token',
            'refresh_token' => $tokens->refreshToken,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        if (! $response->successful()) {
            return $tokens;
        }

        $payload = $response->json() ?: [];

        return TokenSet::make(
            $payload['access_token'] ?? $tokens->accessToken,
            $payload['refresh_token'] ?? $tokens->refreshToken,
            isset($payload['expires_in']) ? time() + (int) $payload['expires_in'] : $tokens->expiresAt,
            $tokens->scopes,
        );
    }

    public function revoke(SocialAccount $account): void
    {
        // Most platforms treat a revoked grant as a failed publish, and none
        // of them require the revocation call for the local record to be
        // deleted. The base implementation is deliberately a no-op; adapters
        // whose platform supports revocation override it.
    }

    public function mapError(Throwable $e): NormalizedError
    {
        if ($e instanceof RequestException) {
            $response = $e->response;

            return $this->mapResponse($response);
        }

        if ($e instanceof ConnectionException) {
            return NormalizedError::transient('connection_failed', 'Could not reach the platform.', 30);
        }

        return NormalizedError::permanent('unexpected_error', $e->getMessage());
    }

    /**
     * Reduce an HTTP failure to a retry decision.
     */
    protected function mapResponse(Response $response): NormalizedError
    {
        $status = $response->status();
        $payload = $response->json();
        $message = $this->errorMessage($payload) ?? ($response->body() ?: 'The platform rejected the request.');

        if ($status === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?: 60);

            return NormalizedError::transient('rate_limited', $message, $retryAfter);
        }

        if (in_array($status, [401, 403], true)) {
            return NormalizedError::permanent('token_rejected', $message);
        }

        if ($status >= 500) {
            return NormalizedError::transient('server_error', $message, 60);
        }

        return NormalizedError::permanent('rejected', $message);
    }

    /**
     * Pull a human message out of a provider's error payload.
     *
     * @param  mixed  $payload
     */
    protected function errorMessage($payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $error = Arr::get($payload, 'error');

        if (is_string($error)) {
            return $error;
        }

        if (is_array($error)) {
            $message = Arr::get($error, 'message');

            return is_string($message) ? $message : null;
        }

        $message = Arr::get($payload, 'message');

        return is_string($message) ? $message : null;
    }

    /**
     * The scopes the platform actually granted.
     *
     * Socialite does not reliably expose the granted scopes: some providers
     * populate `approvedScopes`, most return them space-separated in the
     * token response, and neither is guaranteed. Both are read, and an empty
     * result means "unknown" rather than "none" — the caller treats an
     * unknown scope set as a warning, not a rejection.
     *
     * @return list<string>
     */
    protected function grantedScopes(SocialiteUser $user): array
    {
        if (method_exists($user, 'getApprovedScopes')) {
            $approved = $user->getApprovedScopes();

            if (is_array($approved) && $approved !== []) {
                return array_values(array_filter($approved, 'is_string'));
            }
        }

        $raw = $user->user;
        $scope = null;

        if (is_array($raw)) {
            $scope = Arr::get($raw, 'scope') ?? Arr::get($raw, 'scopes');
        }

        if (is_string($scope)) {
            return array_values(array_filter(preg_split('/[\s,]+/', $scope) ?: []));
        }

        if (is_array($scope)) {
            return array_values(array_filter($scope, 'is_string'));
        }

        return [];
    }

    protected function clientId(): ?string
    {
        $value = Arr::get($this->config, 'client_id');

        return is_string($value) ? $value : null;
    }

    protected function clientSecret(): ?string
    {
        $value = Arr::get($this->config, 'client_secret');

        return is_string($value) ? $value : null;
    }

    protected function tokenUrl(): string
    {
        return (string) Arr::get($this->config, 'token_url', '');
    }

    /**
     * A JSON POST with the platform's bearer token attached.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function post(string $url, array $payload, ?string $accessToken = null): Response
    {
        $request = Http::acceptJson()->asJson();

        if ($accessToken !== null && $accessToken !== '') {
            $request = $request->withToken($accessToken);
        }

        return $request->post($url, $payload);
    }

    /**
     * A JSON GET with the platform's bearer token attached.
     *
     * @param  array<string, mixed>  $query
     */
    protected function get(string $url, array $query = [], ?string $accessToken = null): Response
    {
        $request = Http::acceptJson();

        if ($accessToken !== null && $accessToken !== '') {
            $request = $request->withToken($accessToken);
        }

        return $request->get($url, $query);
    }

    /**
     * Publish is deliberately abstract: every platform's write API differs
     * enough that a shared implementation would be a lowest-common-denominator
     * bug factory. The shared parts live in the helpers above.
     */
    abstract public function publish(PostContent $content, SocialAccount $account): PublishResult;

    abstract public function discoverAccounts(TokenSet $tokens): array;

    abstract public function capabilities(): SocialCapabilities;
}
