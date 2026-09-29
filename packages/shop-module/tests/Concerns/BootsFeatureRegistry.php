<?php

namespace Nreljed\ShopModule\Tests\Concerns;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Nreljed\Platform\Services\FeatureRegistry;

/**
 * The minimal container harness the feature-facing contract tests run on: a
 * plain (not Laravel) container backing the FeatureRegistry so a real module
 * provider can register its descriptors and be aggregated by the true kernel.
 *
 * Shared by every *ResourceContractTest (one per converted resource) — the
 * registry's fail-loud guarantees for duplicate keys / drift are already
 * covered by ModuleResourceContractTest, so these use the real provider.
 */
trait BootsFeatureRegistry
{
    private Container $container;

    private ?FeatureRegistry $registry = null;

    protected function bootFeatureRegistry(): void
    {
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

    protected function registerFeatureProvider(object $provider, string $class): void
    {
        $this->container->registerProvider($provider, $class);
        $this->container['config']->set('features.providers', array_merge(
            (array) $this->container['config']->get('features.providers', []),
            [$class],
        ));
    }

    protected function enableFeature(string $slug): void
    {
        $this->container['config']->set("features.enabled.{$slug}", true);
    }

    protected function registry(): FeatureRegistry
    {
        return $this->registry ??= new FeatureRegistry();
    }
}