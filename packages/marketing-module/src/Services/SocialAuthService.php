<?php

namespace Nreljed\MarketingModule\Services;

use InvalidArgumentException;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Nreljed\MarketingModule\Enums\SocialAccountStatus;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\Platform\Models\User;
use RuntimeException;

/**
 * The OAuth handshake for social accounts.
 *
 * Socialite owns the protocol; this service owns the parts that are the
 * module's business — which platform is being connected, where the callback
 * lands, and how the resulting grant becomes a `social_accounts` row.
 *
 * Every platform reads its credentials from `services.<driver>`, which is
 * where Socialite looks. The module merges its own OAuth app into that entry
 * at boot, so a consumer never has to copy credentials into its own
 * `config/services.php`. The module's values win where they are set, and the
 * host app's entry fills the gaps.
 */
class SocialAuthService
{
    public function __construct(
        protected SocialChannelRegistry $registry,
        protected TokenVault $vault,
    ) {}

    /**
     * The module's OAuth configuration for a platform.
     *
     * @return array<string, mixed>
     */
    public function appConfig(string $platform): array
    {
        $app = config("marketing.oauth.apps.{$platform}");

        if (! is_array($app)) {
            throw new InvalidArgumentException("No OAuth application is configured for [{$platform}].");
        }

        return $app;
    }

    /**
     * The Socialite driver a platform authenticates through.
     */
    public function driver(string $platform): string
    {
        return (string) ($this->appConfig($platform)['driver'] ?? $platform);
    }

    /**
     * Merge the module's OAuth app into the driver's `services` entry.
     *
     * The module's values win where they are set; the host app's entry fills
     * anything the module leaves blank. The redirect always points at the
     * module's callback, because the host's redirect belongs to a different
     * flow.
     */
    public function applyServiceConfig(string $platform): void
    {
        $app = $this->appConfig($platform);
        $driver = $this->driver($platform);
        $existing = (array) config("services.{$driver}", []);

        $module = array_filter([
            'client_id' => $app['client_id'] ?? null,
            'client_secret' => $app['client_secret'] ?? null,
        ], static fn ($value): bool => $value !== null && $value !== '');

        // The redirect always belongs to this module. A host app's
        // `services.<driver>.redirect` was registered for a different flow,
        // and reusing it sends the user back to somebody else's callback
        // with a code this module cannot redeem.
        $module['redirect'] = $this->value($app['redirect'] ?? null) ?? $this->callbackUrl($platform);

        config(["services.{$driver}" => array_merge($existing, $module)]);
    }

    /**
     * The module's OAuth callback for a platform.
     */
    public function callbackUrl(string $platform): string
    {
        return route('dashboard.marketing.accounts.callback', ['platform' => $platform]);
    }

    /**
     * The Socialite provider for a platform, with the module's OAuth app
     * merged into the driver's service configuration and the platform's
     * scopes applied.
     */
    public function provider(string $platform): SocialiteProvider
    {
        $this->applyServiceConfig($platform);

        $driver = $this->driver($platform);
        $merged = (array) config("services.{$driver}", []);

        if ($this->value($merged['client_id'] ?? null) === null
            || $this->value($merged['client_secret'] ?? null) === null) {
            throw new RuntimeException(
                "The {$platform} OAuth application is not configured. Add its client id and secret to the marketing config."
            );
        }

        $app = $this->appConfig($platform);
        $socialite = Socialite::driver($driver);

        $scopes = array_values(array_filter(
            (array) ($app['scopes'] ?? []),
            static fn ($scope): bool => is_string($scope) && $scope !== '',
        ));

        if ($scopes !== []) {
            $socialite = $socialite->scopes($scopes);
        }

        return $socialite;
    }

    /**
     * A config value, or null when it is absent or blank.
     */
    protected function value(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Send the user to the platform's consent screen.
     */
    public function redirectUrl(string $platform): string
    {
        return $this->provider($platform)->redirect();
    }

    /**
     * Turn a completed OAuth handshake into a stored account.
     *
     * Reconnecting an account updates the existing row rather than creating a
     * second one, and preserves anything the user chose locally — a
     * Pinterest board, for instance — that the platform's response does not
     * carry.
     */
    public function resolve(User $user, string $platform, SocialiteUser $socialiteUser): SocialAccount
    {
        $adapter = $this->registry->driver($platform);
        $tokens = $adapter->hydrateTokenSet($socialiteUser);
        $candidates = $adapter->discoverAccounts($tokens);

        if ($candidates === []) {
            throw new RuntimeException(
                "The {$platform} account returned no publishable accounts. Check the app's permissions."
            );
        }

        $candidate = $candidates[0];

        $existing = SocialAccount::query()
            ->where('user_id', $user->id)
            ->where('platform', $platform)
            ->where('account_id', $candidate['account_id'])
            ->first();

        $attributes = [
            'account_name' => $candidate['account_name'],
            'username' => $candidate['username'] ?? null,
            'access_token_encrypted' => $this->vault->encrypt(
                $candidate['access_token'] ?? $tokens->accessToken
            ),
            'refresh_token_encrypted' => $this->vault->encrypt($tokens->refreshToken),
            'scopes' => $tokens->scopes,
            'expires_at' => $tokens->expiresAt !== null
                ? date('Y-m-d H:i:s', $tokens->expiresAt)
                : null,
            'status' => SocialAccountStatus::Active,
            'last_validated_at' => now(),
            'metadata' => array_merge(
                (array) ($candidate['metadata'] ?? []),
                (array) ($existing?->metadata ?? []),
            ),
        ];

        $account = SocialAccount::updateOrCreate(
            [
                'user_id' => $user->id,
                'platform' => $platform,
                'account_id' => $candidate['account_id'],
            ],
            $attributes,
        );

        return $account;
    }
}
