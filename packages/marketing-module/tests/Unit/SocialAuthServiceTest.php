<?php

namespace Nreljed\MarketingModule\Tests\Unit;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Nreljed\MarketingModule\Contracts\SocialChannelAdapter;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Services\SocialAuthService;
use Nreljed\MarketingModule\Services\SocialChannelRegistry;
use Nreljed\MarketingModule\Services\TokenVault;
use Nreljed\MarketingModule\Support\NormalizedError;
use Nreljed\MarketingModule\Support\PostContent;
use Nreljed\MarketingModule\Support\PublishResult;
use Nreljed\MarketingModule\Support\SocialCapabilities;
use Nreljed\MarketingModule\Support\TokenSet;
use PHPUnit\Framework\TestCase;
use Throwable;

final class SocialAuthServiceTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        Container::setInstance($this->container);
        Facade::setFacadeApplication($this->container);
        Facade::clearResolvedInstances();

        $this->container->instance('config', new ConfigRepository([
            'marketing' => [
                'oauth' => [
                    'apps' => [
                        'instagram' => [
                            'driver' => 'instagram',
                            'client_id' => 'module-client-id',
                            'client_secret' => 'module-client-secret',
                            'redirect' => 'https://example.test/dashboard/marketing/accounts/callback/instagram',
                            'scopes' => ['instagram_business_basic'],
                        ],
                        'pinterest' => [
                            'driver' => 'pinterest',
                            'scopes' => ['user_accounts:read'],
                        ],
                    ],
                ],
            ],
            'services' => [
                'instagram' => [
                    'client_id' => 'host-client-id',
                    'client_secret' => 'host-client-secret',
                    'redirect' => 'https://example.test/host/other/callback',
                ],
            ],
        ]));
    }

    public function test_it_rejects_an_unknown_platform(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->appConfig('tiktok');
    }

    public function test_it_resolves_the_configured_driver(): void
    {
        $this->assertSame('instagram', $this->service()->driver('instagram'));
        $this->assertSame('pinterest', $this->service()->driver('pinterest'));
    }

    public function test_the_module_credentials_win_over_the_host_entry(): void
    {
        $this->service()->applyServiceConfig('instagram');

        $services = (array) config('services.instagram');

        $this->assertSame('module-client-id', $services['client_id']);
        $this->assertSame('module-client-secret', $services['client_secret']);
    }

    public function test_the_redirect_always_points_at_the_module_callback(): void
    {
        $this->service()->applyServiceConfig('instagram');

        $services = (array) config('services.instagram');

        $this->assertSame(
            'https://example.test/dashboard/marketing/accounts/callback/instagram',
            $services['redirect'],
            'A host app redirect belongs to a different flow and must not be reused.'
        );
    }

    public function test_the_host_entry_fills_the_gaps_the_module_leaves(): void
    {
        $this->container['config']->set('marketing.oauth.apps.pinterest.client_id', 'module-client-id');
        $this->container['config']->set('marketing.oauth.apps.pinterest.client_secret', 'module-client-secret');
        $this->container['config']->set('services.pinterest', [
            'client_id' => 'host-client-id',
            'client_secret' => 'host-client-secret',
            'redirect' => 'https://example.test/host/other/callback',
        ]);
        $this->container['config']->set('marketing.oauth.apps.pinterest.redirect', 'https://example.test/module/callback');

        $this->service()->applyServiceConfig('pinterest');

        $services = (array) config('services.pinterest');

        $this->assertSame('module-client-id', $services['client_id']);
        $this->assertSame('https://example.test/module/callback', $services['redirect']);
    }

    public function test_the_registry_rejects_an_unsupported_channel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SocialChannelRegistry(['default' => 'meta', 'drivers' => ['meta' => FakeAdapter::class]]))
            ->driver('tiktok');
    }

    public function test_the_registry_lists_the_configured_platforms(): void
    {
        $registry = new SocialChannelRegistry([
            'default' => 'meta',
            'drivers' => ['meta' => FakeAdapter::class, 'instagram' => FakeAdapter::class],
        ]);

        $this->assertTrue($registry->has('instagram'));
        $this->assertFalse($registry->has('pinterest'));
        $this->assertSame(['meta', 'instagram'], $registry->platforms());
    }

    private function service(): SocialAuthService
    {
        return new SocialAuthService(
            new SocialChannelRegistry(['default' => 'meta', 'drivers' => ['meta' => FakeAdapter::class]]),
            new TokenVault,
        );
    }
}

/**
 * A stand-in adapter: the registry only needs to resolve a class, and the
 * service only needs to reach `hydrateTokenSet()` and `discoverAccounts()`.
 */
final class FakeAdapter implements SocialChannelAdapter
{
    public function platform(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Fake';
    }

    public function capabilities(): SocialCapabilities
    {
        return new SocialCapabilities;
    }

    public function requiredScopes(): array
    {
        return [];
    }

    public function socialiteDriver(): string
    {
        return 'fake';
    }

    public function hydrateTokenSet(SocialiteUser $user): TokenSet
    {
        return new TokenSet('token');
    }

    public function discoverAccounts(TokenSet $tokens): array
    {
        return [];
    }

    public function refreshTokens(TokenSet $tokens): TokenSet
    {
        return $tokens;
    }

    public function publish(PostContent $content, SocialAccount $account): PublishResult
    {
        return PublishResult::failed('not_implemented', 'The fake adapter does not publish.');
    }

    public function fetchMetrics(string $externalPostId, SocialAccount $account): array
    {
        return [];
    }

    public function revoke(SocialAccount $account): void
    {
        //
    }

    public function mapError(Throwable $e): NormalizedError
    {
        return NormalizedError::permanent('unknown', $e->getMessage());
    }
}
