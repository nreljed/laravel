<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Support\ServiceProvider;

class FeatureRegistry
{
    /** @var array<string, ServiceProvider> */
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
     * @template T of \Illuminate\Support\ServiceProvider
     *
     * @param  class-string<T>  $class
     * @return T
     */
    protected function getProviderOrFail(string $class): ServiceProvider
    {
        $provider = app()->getProvider($class);

        if ($provider === null) {
            throw new \RuntimeException("Service provider [{$class}] is not registered.");
        }

        return $provider;
    }

    public function getAllNavItems(): array
    {
        return array_merge(...array_map(
            fn (ServiceProvider $provider) => method_exists($provider, 'getNavItems') ? $provider->getNavItems() : [],
            array_values($this->providers),
        ));
    }

    public function getAllSharedData(): array
    {
        return array_merge(...array_map(
            fn (ServiceProvider $provider) => method_exists($provider, 'getSharedData') ? $provider->getSharedData() : [],
            array_values($this->providers),
        ));
    }

    public function getAllDashboardData(): array
    {
        $data = [];

        foreach ($this->providers as $provider) {
            if (! method_exists($provider, 'getDashboardData')) {
                continue;
            }

            $featureData = $provider->getDashboardData();

            if (! empty($featureData) && method_exists($provider, 'slug')) {
                $data[$provider->slug()] = $featureData;
            }
        }

        return $data;
    }

    public function getAllAnalyticsSeries(): array
    {
        $series = [];

        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'getAnalyticsSeries')) {
                $series = array_merge($series, $provider->getAnalyticsSeries());
            }
        }

        return $series;
    }

    public function getAllNotificationTypes(): array
    {
        return array_merge(...array_map(
            fn (ServiceProvider $provider) => method_exists($provider, 'getNotificationTypes') ? $provider->getNotificationTypes() : [],
            array_values($this->providers),
        ));
    }

    public function getAllEmailTemplates(): array
    {
        return array_merge(...array_map(
            fn (ServiceProvider $provider) => method_exists($provider, 'getEmailTemplates') ? $provider->getEmailTemplates() : [],
            array_values($this->providers),
        ));
    }

    public function getAllGalleryConfigs(): array
    {
        return array_merge(...array_map(
            fn (ServiceProvider $provider) => method_exists($provider, 'getGalleryConfig') ? $provider->getGalleryConfig() : (method_exists($provider, 'getGalleryConfigs') ? $provider->getGalleryConfigs() : []),
            array_values($this->providers),
        ));
    }

    public function getAllMetaFormattingConfigs(): array
    {
        return array_merge(...array_map(
            fn (ServiceProvider $provider) => method_exists($provider, 'getMetaFormattingConfig') ? $provider->getMetaFormattingConfig() : [],
            array_values($this->providers),
        ));
    }

    public function getAllRepeaterConfigs(): array
    {
        return array_merge(...array_map(
            fn (ServiceProvider $provider) => method_exists($provider, 'getRepeaterConfig') ? $provider->getRepeaterConfig() : [],
            array_values($this->providers),
        ));
    }

    public function getAllProfileFieldConfigs(): array
    {
        return array_merge(...array_map(
            fn (ServiceProvider $provider) => method_exists($provider, 'getProfileFieldsConfig') ? $provider->getProfileFieldsConfig() : [],
            array_values($this->providers),
        ));
    }

    public function getAllSettingsRoutes(): array
    {
        return array_filter(array_map(
            fn (ServiceProvider $provider) => method_exists($provider, 'getSettingsRoute') ? $provider->getSettingsRoute() : null,
            array_values($this->providers),
        ));
    }

    public function getAllHasSettings(): array
    {
        return array_filter(array_map(
            fn (ServiceProvider $provider) => method_exists($provider, 'hasSettings') ? $provider->hasSettings() : false,
            array_values($this->providers),
        ));
    }

    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->providers as $provider) {
            if (! method_exists($provider, 'permissions')) {
                continue;
            }

            $group = method_exists($provider, 'name') ? $provider->name() : null;

            foreach ($provider->permissions() as $permission) {
                if (is_array($permission) && ! isset($permission['group']) && $group !== null) {
                    $permission['group'] = $group;
                }

                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    public function getAll(): array
    {
        return array_map(
            fn (ServiceProvider $provider) => array_filter([
                'name' => method_exists($provider, 'name') ? $provider->name() : class_basename($provider),
                'slug' => method_exists($provider, 'slug') ? $provider->slug() : null,
                'description' => method_exists($provider, 'description') ? $provider->description() : null,
                'version' => method_exists($provider, 'version') ? $provider->version() : null,
                'dependencies' => method_exists($provider, 'dependencies') ? $provider->dependencies() : [],
                'enabled' => true,
                'has_settings' => method_exists($provider, 'hasSettings') ? $provider->hasSettings() : false,
                'settings_route' => method_exists($provider, 'getSettingsRoute') ? $provider->getSettingsRoute() : null,
            ], fn ($v) => $v !== null && $v !== [] && $v !== false),
            array_values($this->providers),
        );
    }

    public function cleanupUserData(int $userId): void
    {
        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'onUserDelete')) {
                $provider->onUserDelete($userId);
            }
        }
    }
}
