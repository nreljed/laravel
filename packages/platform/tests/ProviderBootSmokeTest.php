<?php

namespace Nreljed\Platform\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use Nreljed\Platform\Contracts\Capabilities\HasPermissions;
use Nreljed\Platform\Contracts\Feature;
use PHPUnit\Framework\TestCase;

/**
 * Smoke test for every provider in config/features.php.
 *
 * WS-1's root cause was a provider that was configured but not bootable: the
 * entry survived in `bootstrap/providers.php` while its Composer requirement
 * had been reverted, and Laravel 13 silently drops providers whose class does
 * not exist. This test asserts the same invariants from inside the package, so
 * a broken provider fails the package's own CI before any consumer sees it.
 *
 * A minimal container is installed so `enabled()` / `version()` (which fall
 * back to config through `app()`) resolve deterministically without booting a
 * full application.
 */
final class ProviderBootSmokeTest extends TestCase
{
    private ?Container $previousContainer = null;

    /** @var array<string, mixed> */
    private array $features;

    protected function setUp(): void
    {
        parent::setUp();

        $this->features = require dirname(__DIR__).'/config/features.php';

        $this->previousContainer = Container::getInstance();

        $container = new Container;
        $container->instance('config', new Repository(['features' => $this->features]));

        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    public function test_the_package_configures_at_least_one_provider(): void
    {
        $this->assertNotEmpty($this->configuredProviders(), 'config/features.php declares no providers.');
    }

    public function test_every_configured_provider_is_bootable(): void
    {
        $violations = [];

        foreach ($this->configuredProviders() as $class) {
            if (! class_exists($class)) {
                $violations[] = "{$class} does not exist — Laravel would silently drop it.";

                continue;
            }

            $reflection = new \ReflectionClass($class);

            if (! $reflection->isSubclassOf(ServiceProvider::class)) {
                $violations[] = "{$class} must extend ".ServiceProvider::class.'.';
            }

            if (! $reflection->implementsInterface(Feature::class)) {
                $violations[] = "{$class} must implement ".Feature::class.' (or the deprecated FeatureProvider composite).';
            }

            if (! $reflection->isInstantiable()) {
                $violations[] = "{$class} is not instantiable.";
            }
        }

        $this->assertSame([], $violations, 'Provider boot violations:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_every_provider_reports_a_sane_identity(): void
    {
        $violations = [];
        $slugs = [];

        foreach ($this->instances() as $class => $provider) {
            $name = $provider->name();

            if (trim($name) === '') {
                $violations[] = "{$class}::name() is empty.";
            }

            $slug = $provider->slug();

            if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1) {
                $violations[] = "{$class}::slug() [{$slug}] must be lowercase kebab-case.";
            }

            if (isset($slugs[$slug])) {
                $violations[] = "slug [{$slug}] is claimed by both {$slugs[$slug]} and {$class}.";
            }

            $slugs[$slug] = $class;

            $description = $provider->description();

            if ($description !== null && trim($description) === '') {
                $violations[] = "{$class}::description() must be null or a non-empty string.";
            }

            if (preg_match('/^\d+\.\d+\.\d+/', $provider->version()) !== 1) {
                $violations[] = "{$class}::version() [{$provider->version()}] is not semver-shaped.";
            }

            $package = $provider->getComposerPackage();

            if ($package !== null && preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#', $package) !== 1) {
                $violations[] = "{$class}::getComposerPackage() [{$package}] must be null or vendor/package.";
            }

            if (! is_bool($provider->enabled())) {
                $violations[] = "{$class}::enabled() must return a bool.";
            }
        }

        $this->assertSame([], $violations, 'Provider identity violations:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_permission_entries_are_well_formed_and_scoped_to_their_provider(): void
    {
        $violations = [];

        foreach ($this->instances() as $class => $provider) {
            $slug = $provider->slug();

            foreach ($this->permissionsOf($provider) as $permission) {
                if (! is_array($permission) || ! isset($permission['slug'], $permission['name'])) {
                    $violations[] = "{$class} declares a permission without a slug and name.";

                    continue;
                }

                $permissionSlug = (string) $permission['slug'];

                if (preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/', $permissionSlug) !== 1) {
                    $violations[] = "{$class} declares malformed permission slug [{$permissionSlug}].";

                    continue;
                }

                // Dotted slugs belong to a feature namespace (posts.view). The
                // namespace must be the provider's own slug or a prefix of it.
                if (str_contains($permissionSlug, '.')) {
                    $namespace = strstr($permissionSlug, '.', true);

                    if ($namespace !== $slug && ! str_starts_with($slug, $namespace)) {
                        $violations[] = "{$class} declares permission [{$permissionSlug}] outside its slug namespace [{$slug}].";
                    }
                }
            }
        }

        $this->assertSame([], $violations, 'Permission shape violations:'.PHP_EOL.implode(PHP_EOL, $violations));
    }

    public function test_the_enabled_map_covers_exactly_the_configured_providers(): void
    {
        $configuredSlugs = [];

        foreach ($this->instances() as $provider) {
            $configuredSlugs[] = $provider->slug();
        }

        sort($configuredSlugs);

        $enabledSlugs = array_keys($this->features['enabled'] ?? []);
        sort($enabledSlugs);

        $this->assertSame(
            $configuredSlugs,
            $enabledSlugs,
            'config/features.php "enabled" must list every provider slug exactly once — '
                .'a missing key silently disables the feature at runtime.',
        );
    }

    /**
     * @return list<class-string>
     */
    private function configuredProviders(): array
    {
        $providers = $this->features['providers'] ?? [];

        $this->assertIsArray($providers, 'config/features.php "providers" must be an array.');

        return array_values(array_map('strval', $providers));
    }

    /**
     * @return array<class-string, Feature>
     */
    private function instances(): array
    {
        $container = Container::getInstance();

        $this->assertNotNull($container);

        $instances = [];

        foreach ($this->configuredProviders() as $class) {
            if (! class_exists($class)) {
                continue;
            }

            $provider = new $class($container);

            if ($provider instanceof Feature) {
                $instances[$class] = $provider;
            }
        }

        return $instances;
    }

    /**
     * @return list<mixed>
     */
    private function permissionsOf(Feature $provider): array
    {
        // permissions() is a pure declaration on every shipped provider; it is
        // reached through the capability interface rather than the composite so
        // a minimal Feature that declares nothing is simply skipped.
        if (! $provider instanceof HasPermissions) {
            return [];
        }

        return $provider->permissions();
    }
}
