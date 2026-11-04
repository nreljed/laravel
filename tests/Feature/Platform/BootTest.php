<?php

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Contracts\FeatureProvider;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Services\FeatureRegistry;
use Tests\TestCase;

/**
 * Boot-time wiring guards for the app shell.
 *
 * The failure mode these exist to catch: Laravel's `RegisterProviders` bootstrapper
 * silently drops any provider class listed in `bootstrap/providers.php` that cannot
 * be autoloaded (`RegisterProviders::mergeAdditionalProviders()` calls `class_exists()`
 * and `unset()`s the entry). A package removed from `composer.json` therefore does not
 * fatal — it just quietly disappears from the app while the frontend keeps importing
 * its page kit. That is exactly how `nreljed/marketing-module` was lost: the
 * `bootstrap/providers.php` entry and `page-config.ts` import survived, but
 * `composer.json` did not, so the backend feature vanished with no error.
 */
class BootTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * The application boots without error.
     */
    public function test_the_application_boots(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertTrue(app()->bound(FeatureRegistry::class));
    }

    /**
     * Every provider in bootstrap/providers.php must be a real, autoloadable class.
     *
     * This is the guard that a missing Composer package cannot slip past: Laravel
     * would drop it silently, so we assert it explicitly.
     */
    public function test_every_bootstrap_provider_class_exists(): void
    {
        $path = app()->getBootstrapProvidersPath();
        $this->assertFileExists($path);

        /** @var array<int, string|null> $providers */
        $providers = require $path;
        $this->assertNotEmpty($providers);

        foreach (array_filter($providers) as $provider) {
            $this->assertTrue(
                class_exists($provider),
                "bootstrap/providers.php lists [{$provider}] but the class does not exist. ".
                'It was silently dropped by Laravel — check the owning package is required in composer.json.'
            );
        }
    }

    /**
     * Every provider in bootstrap/providers.php must actually be registered.
     */
    public function test_every_bootstrap_provider_is_registered(): void
    {
        /** @var array<int, string|null> $providers */
        $providers = require app()->getBootstrapProvidersPath();

        foreach (array_filter($providers) as $provider) {
            $this->assertNotNull(
                app()->getProvider($provider),
                "Service provider [{$provider}] is listed in bootstrap/providers.php but was never registered."
            );
        }
    }

    /**
     * Every provider in config/features.php must implement the feature contract.
     */
    public function test_every_configured_feature_provider_is_registered_and_implements_the_contract(): void
    {
        $providers = config('features.providers', []);
        $this->assertNotEmpty($providers);

        foreach ($providers as $class) {
            $provider = app()->getProvider($class);

            $this->assertNotNull($provider, "Feature provider [{$class}] is not registered.");
            $this->assertInstanceOf(FeatureProvider::class, $provider);
            $this->assertNotSame('', $provider->slug(), "Feature provider [{$class}] must expose a slug.");
        }
    }

    /**
     * The registry exposes exactly the slugs configured in config/features.php.
     *
     * Catches a provider that is configured but fails to resolve into the registry,
     * and a stale `enabled` entry for a feature that no longer exists.
     */
    public function test_the_registry_slugs_match_the_configured_providers(): void
    {
        $registry = app(FeatureRegistry::class);

        $registered = array_values(array_unique(array_map(
            static fn (FeatureProvider $provider): string => $provider->slug(),
            $registry->allProviders(),
        )));
        sort($registered);

        $configured = [];
        foreach (config('features.providers', []) as $class) {
            $provider = app()->getProvider($class);
            $this->assertNotNull($provider, "Feature provider [{$class}] is not registered.");
            $configured[] = $provider->slug();
        }
        $configured = array_values(array_unique($configured));
        sort($configured);

        $this->assertSame($configured, $registered);

        $unknownDefaults = array_diff(array_keys(config('features.enabled', [])), $configured);
        $this->assertSame(
            [],
            array_values($unknownDefaults),
            'config/features.php `enabled` contains slugs with no registered provider.'
        );
    }

    /**
     * The registry reports no dependency or contract problems.
     */
    public function test_the_feature_registry_validates_cleanly(): void
    {
        $this->assertSame([], app(FeatureRegistry::class)->validate());
    }

    /**
     * The public site answers, i.e. routing + middleware + Inertia are wired.
     */
    public function test_the_public_page_responds(): void
    {
        $this->get('/')->assertOk();
    }
}
