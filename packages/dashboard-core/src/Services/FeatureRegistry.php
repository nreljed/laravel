<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Support\ServiceProvider;
use Nreljed\DashboardCore\Contracts\FeatureProvider;

class FeatureRegistry
{
    /** @var array<string, FeatureProvider> */
    protected array $providers = [];

    public function __construct()
    {
        foreach ($this->configuredProviders() as $class) {
            $this->providers[$class] = $this->getProviderOrFail($class);
        }
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    protected function configuredProviders(): array
    {
        return config('core.features', []);
    }

    /**
     * @param  class-string<ServiceProvider>  $class
     */
    protected function getProviderOrFail(string $class): FeatureProvider
    {
        $provider = app()->getProvider($class);

        if ($provider === null) {
            throw new \RuntimeException("Service provider [{$class}] is not registered.");
        }

        if (! $provider instanceof FeatureProvider) {
            throw new \RuntimeException(
                "Service provider [{$class}] must implement ".FeatureProvider::class.'.'
            );
        }

        return $provider;
    }

    public function getAllNavItems(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getNavItems(), array_values($this->providers))
        );
    }

    public function getAllSharedData(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getSharedData(), array_values($this->providers))
        );
    }

    public function getAllDashboardData(): array
    {
        $data = [];

        foreach ($this->providers as $provider) {
            $featureData = $provider->getDashboardData();

            if (! empty($featureData)) {
                $data[$provider->slug()] = $featureData;
            }
        }

        return $data;
    }

    public function getAllAnalyticsSeries(): array
    {
        $series = [];

        foreach ($this->providers as $provider) {
            $series = array_merge($series, $provider->getAnalyticsSeries());
        }

        return $series;
    }

    public function getAllNotificationTypes(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getNotificationTypes(), array_values($this->providers))
        );
    }

    public function getAllEmailTemplates(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getEmailTemplates(), array_values($this->providers))
        );
    }

    public function getAllGalleryConfigs(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getGalleryConfig(), array_values($this->providers))
        );
    }

    public function getAllMetaFormattingConfigs(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getMetaFormattingConfig(), array_values($this->providers))
        );
    }

    public function getAllRepeaterConfigs(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getRepeaterConfig(), array_values($this->providers))
        );
    }

    public function getAllProfileFieldConfigs(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getProfileFieldsConfig(), array_values($this->providers))
        );
    }

    public function getAllSettingsRoutes(): array
    {
        return array_filter(array_map(
            fn (FeatureProvider $provider) => $this->resolveSettingsUrl($provider->getSettingsRoute()),
            array_values($this->providers),
        ));
    }

    public function getAllHasSettings(): array
    {
        return array_filter(array_map(
            fn (FeatureProvider $provider) => $provider->hasSettings(),
            array_values($this->providers),
        ));
    }

    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->providers as $provider) {
            $group = $provider->name();

            foreach ($provider->permissions() as $permission) {
                if (is_array($permission) && ! isset($permission['group']) && $group !== null) {
                    $permission['group'] = $group;
                }

                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * @return array<int, FeatureProvider>
     */
    public function allProviders(): array
    {
        return array_values($this->providers);
    }

    public function getAll(): array
    {
        return array_map(
            fn (FeatureProvider $provider) => array_filter([
                'name' => $provider->name(),
                'slug' => $provider->slug(),
                'description' => $provider->description(),
                'version' => $provider->version(),
                'dependencies' => $provider->dependencies(),
                'enabled' => true,
                'has_settings' => $provider->hasSettings(),
                'settings_route' => $this->resolveSettingsUrl($provider->getSettingsRoute()),
            ], fn ($v) => $v !== null && $v !== [] && $v !== false),
            array_values($this->providers),
        );
    }

    public function cleanupUserData(int $userId): void
    {
        foreach ($this->providers as $provider) {
            $provider->onUserDelete($userId);
        }
    }

    /**
     * Resolve a settings route name to a URL, degrading to null when the
     * route does not exist so one stale provider cannot break the registry.
     */
    protected function resolveSettingsUrl(?string $routeName): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        try {
            return route($routeName, [], false);
        } catch (\Throwable) {
            return null;
        }
    }
}
