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
        $items = [];

        foreach ($this->providers as $provider) {
            $groupLabel = $provider->getNavGroupLabel();

            foreach ($provider->getNavItems() as $item) {
                // Normalize group labels so the sidebar renders consistent
                // headings even when a provider only exposes getNavGroupLabel().
                if ($groupLabel !== null && ! isset($item['groupLabel'])) {
                    $item['groupLabel'] = $groupLabel;
                }

                $items[] = $item;
            }
        }

        return $items;
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
                $data[$provider->slug()] = $this->normalizeDashboardData($featureData, $provider->slug());
            }
        }

        return $data;
    }

    /**
     * Guarantee the reserved keys the dashboard renders (label, icon, total)
     * are always present so a partial provider payload degrades gracefully
     * instead of producing malformed tiles.
     */
    protected function normalizeDashboardData(array $data, string $slug): array
    {
        $reserved = ['label', 'icon', 'total'];

        $data['label'] ??= ucfirst($slug);
        $data['icon'] ??= 'FileText';

        // Derive "total" from the first numeric value when the provider did
        // not explicitly declare one, so the primary KPI card still renders.
        if (! array_key_exists('total', $data)) {
            foreach ($data as $key => $value) {
                if (in_array($key, $reserved, true)) {
                    continue;
                }

                if (is_numeric($value)) {
                    $data['total'] = $value;
                    unset($data[$key]);

                    break;
                }
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

    /**
     * Settings navigation entries for providers that expose a settings page,
     * ready to be rendered by the admin settings sidebar.
     *
     * @return array<int, array{label: string, route: string, url: string}>
     */
    public function getAllSettingsNav(): array
    {
        $nav = [];

        foreach ($this->providers as $provider) {
            if (! $provider->hasSettings()) {
                continue;
            }

            $route = $provider->getSettingsRoute();
            $url = $this->resolveSettingsUrl($route);

            if ($route === null || $url === null) {
                continue;
            }

            $nav[] = [
                'label' => $provider->name(),
                'route' => $route,
                'url' => $url,
            ];
        }

        return $nav;
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
     * Structural validation of the registered feature providers. Used by the
     * system health page to surface misconfigurations that would otherwise
     * fail silently (duplicate slugs, broken dependencies, unresolvable
     * settings routes, malformed dashboard data).
     *
     * @return array<int, array{severity: string, provider: string, message: string}>
     */
    public function validate(): array
    {
        $issues = [];

        $slugs = [];
        $bySlug = [];

        foreach ($this->providers as $provider) {
            $slug = $provider->slug();
            $bySlug[$slug] = $provider;
            $slugs[] = $slug;
        }

        $duplicates = array_diff_assoc($slugs, array_unique($slugs));
        foreach (array_unique($duplicates) as $slug) {
            $issues[] = [
                'severity' => 'critical',
                'provider' => $slug,
                'message' => "Duplicate feature slug [{$slug}] registered by multiple providers.",
            ];
        }

        foreach ($this->providers as $provider) {
            $slug = $provider->slug();

            foreach ($provider->dependencies() as $dependency) {
                if (! array_key_exists($dependency, $bySlug)) {
                    $issues[] = [
                        'severity' => 'high',
                        'provider' => $slug,
                        'message' => "Declares dependency [{$dependency}] but no registered feature provides that slug.",
                    ];
                }
            }

            $settingsRoute = $provider->getSettingsRoute();
            if ($provider->hasSettings() && ($settingsRoute === null || $this->resolveSettingsUrl($settingsRoute) === null)) {
                $issues[] = [
                    'severity' => 'medium',
                    'provider' => $slug,
                    'message' => "Declares a settings page but route [{$settingsRoute}] cannot be resolved.",
                ];
            }

            $dashboardData = $provider->getDashboardData();
            if ($dashboardData !== []) {
                $normalized = $this->normalizeDashboardData($dashboardData, $slug);

                foreach (['label', 'icon', 'total'] as $reserved) {
                    if (! array_key_exists($reserved, $normalized)) {
                        $issues[] = [
                            'severity' => 'medium',
                            'provider' => $slug,
                            'message' => "Dashboard data is missing the reserved [{$reserved}] key.",
                        ];
                    }
                }
            }
        }

        return $issues;
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
