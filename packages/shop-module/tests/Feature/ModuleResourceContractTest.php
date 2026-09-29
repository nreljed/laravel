<?php

namespace Nreljed\ShopModule\Tests\Feature;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\FeatureProvider;
use Nreljed\Platform\Services\FeatureRegistry;
use Nreljed\Platform\Support\ResourceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * The module-shipping contract test the plan calls the kernel guarantee
 * (PLAN §5.4): a structural + behavioral test asserting that every feature
 * module declaring admin resources does so through the FeatureProvider
 * contract, and that the registry - the thing the dashboard renders from -
 * rejects a module that drifts off the shipped schema.
 *
 * Why a hand-rolled container instead of `BootsShopSchema`: the registry
 * resolves providers via `app()->getProvider()`, which is an
 * `Illuminate\Foundation\Application` method the schema harness (a plain
 * Container with no feature provider booted) deliberately does not provide.
 * The two fake modules below exercise exactly what a module vendor ships -
 * a feature provider class returning descriptors - against the real kernel.
 */
final class ModuleResourceContractTest extends TestCase
{
    private Container $container;

    private ?FeatureRegistry $registry = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new class extends Container
        {
            /** @var array<class-string, object> */
            private array $providers = [];

            public function registerProvider(object $provider, string $class): void
            {
                $this->providers[$class] = $provider;
            }

            public function getProvider($provider): ?object
            {
                $class = is_object($provider) ? get_class($provider) : $provider;

                return $this->providers[$class] ?? null;
            }

            public function getProviders($provider): array
            {
                return array_values(array_filter(
                    $this->providers,
                    fn (object $instance) => $instance instanceof $provider,
                ));
            }
        };

        Container::setInstance($this->container);
        Facade::setFacadeApplication($this->container);
        Facade::clearResolvedInstances();

        $config = new ConfigRepository([
            'features' => ['providers' => []],
        ]);
        $this->container->instance('config', $config);

        $this->container->singleton('events', fn () => new Dispatcher(new Container));
    }

    public function test_conforming_descriptors_aggregate_verbatim_from_every_enabled_module(): void
    {
        $this->register(GoodModule::class);

        $resources = $this->registry()->getAllAdminResources();

        $this->assertCount(2, $resources);
        $this->assertContainsOnlyInstancesOf(ResourceDefinition::class, $resources);
        $this->assertSame(['shop.products', 'shop.currencies'], array_map(
            fn (ResourceDefinition $definition) => $definition->getKey(),
            $resources,
        ));
    }

    public function test_a_module_returning_raw_arrays_is_rejected_with_its_name(): void
    {
        $this->register(RawArrayModule::class);

        try {
            $this->registry()->getAllAdminResources();
            $this->fail('The registry must reject a provider that returns raw arrays.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('raw-array-module', $exception->getMessage());
            $this->assertStringContainsString('ResourceDefinition', $exception->getMessage());
        }
    }

    public function test_a_module_that_drifts_off_the_schema_is_rejected_with_its_name_and_its_wrong_field(): void
    {
        $this->register(DriftModule::class);

        try {
            $this->registry()->getAllAdminResources();
            $this->fail('The registry must reject a descriptor that violates the shipped schema.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('Feature [drift-module] declared resource [shop.products]', $exception->getMessage());
            $this->assertStringContainsString('table.columns[0].name must be a non-empty accessor key', $exception->getMessage());
        }
    }

    public function test_duplicate_resource_keys_across_modules_fail_loudly_naming_both(): void
    {
        $this->register(GoodModule::class);
        $this->register(CollidingModule::class);

        try {
            $this->registry()->getAllAdminResources();
            $this->fail('The registry must reject duplicate resource keys.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Duplicate admin resource key [shop.products]', $exception->getMessage());
            $this->assertStringContainsString('good-module', $exception->getMessage());
            $this->assertStringContainsString('colliding-module', $exception->getMessage());
        }
    }

    public function test_drift_is_reported_by_the_system_health_validation_not_just_the_throwing_getter(): void
    {
        $this->register(DriftModule::class);

        $issues = $this->registry()->validate();

        $this->assertNotEmpty($issues);
        $messages = implode("\n", array_map(
            fn (array $issue) => "[{$issue['severity']}] {$issue['message']}",
            $issues,
        ));

        $this->assertStringContainsString('[high] Resource [shop.products]: table.columns[0].name must be a non-empty accessor key', $messages);
    }

    private function register(string $providerClass): void
    {
        $instance = new $providerClass();
        $this->container->registerProvider($instance, $providerClass);
        $this->container['config']->set(
            'features.providers',
            array_merge($this->container['config']->get('features.providers', []), [$providerClass]),
        );

        // A feature in `features.providers` is on unless the per-slug config
        // disables it; declaring the enabled flag explicitly keeps this test
        // independent of the helper's default.
        $this->container['config']->set("features.enabled.{$instance->slug()}", true);
    }

    private function registry(): FeatureRegistry
    {
        return $this->registry ??= new FeatureRegistry();
    }
}

final class GoodModule implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Good module';
    }

    public function slug(): string
    {
        return 'good-module';
    }

    public function getAdminResources(): array
    {
        return [
            new ResourceDefinition(
                key: 'shop.products',
                label: 'Products',
                singular: 'Product',
                table: [
                    'columns' => [
                        ['name' => 'name', 'header' => 'Name'],
                    ],
                ],
                routes: ['index' => 'dashboard.shop.products.index'],
            ),
            new ResourceDefinition(
                key: 'shop.currencies',
                label: 'Currencies',
                routes: ['index' => 'dashboard.shop.currencies.index'],
            ),
        ];
    }
}

final class RawArrayModule implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Raw array module';
    }

    public function slug(): string
    {
        return 'raw-array-module';
    }

    public function getAdminResources(): array
    {
        return [['key' => 'shop.products', 'label' => 'Products']];
    }
}

final class DriftModule implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Drift module';
    }

    public function slug(): string
    {
        return 'drift-module';
    }

    public function getAdminResources(): array
    {
        return [
            new ResourceDefinition(
                key: 'shop.products',
                label: 'Products',
                table: [
                    'columns' => [['header' => 'Name missing its accessor key']],
                ],
                routes: ['edit' => 'dashboard.shop.products.edit'],
            ),
        ];
    }
}

final class CollidingModule implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Colliding module';
    }

    public function slug(): string
    {
        return 'colliding-module';
    }

    public function getAdminResources(): array
    {
        return [
            new ResourceDefinition(
                key: 'shop.products',
                label: 'Their products',
                routes: ['index' => 'dashboard.shop.products.index'],
            ),
        ];
    }
}