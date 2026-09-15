<?php

namespace Nreljed\Platform\Services;

use Illuminate\Support\ServiceProvider;
use Nreljed\Platform\Contracts\FeatureProvider;

class FeatureRegistry
{
    /** @var array<string, FeatureProvider> */
    protected array $providers = [];

    /** @var array<string, mixed> */
    protected array $memoized = [];

    public function __construct()
    {
        foreach ($this->configuredProviders() as $class) {
            $this->providers[$class] = $this->getProviderOrFail($class);
        }
    }

    /**
     * Memoize a deterministic aggregate on the registry instance. Only safe
     * for config-shaped output (nav, settings, permissions, module list)
     * that is free of per-user or live-DB state.
     *
     * @template T
     *
     * @param  (callable():T)  $resolver
     * @return T
     */
    protected function remember(string $key, callable $resolver): mixed
    {
        return $this->memoized[$key] ??= $resolver();
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    protected function configuredProviders(): array
    {
        return config('features.providers', []);
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

    /**
     * Feature providers that are currently enabled.
     *
     * A disabled provider contributes nothing to the aggregators — no nav,
     * dashboard data, analytics, shared data, settings, email templates,
     * notification types or permissions.
     *
     * @return list<FeatureProvider>
     */
    protected function enabledProviders(): array
    {
        return array_values(array_filter(
            $this->providers,
            fn (FeatureProvider $provider) => $provider->enabled()
        ));
    }

    /**
     * Whether every feature dependency of every enabled provider is also
     * enabled. Mirrors the platform module loader's hard dependency check.
     */
    public function missingDependencies(): array
    {
        $bySlug = [];
        foreach (array_values($this->providers) as $provider) {
            $bySlug[$provider->slug()] = $provider;
        }

        $missing = [];

        foreach ($this->providers as $provider) {
            if (! $provider->enabled()) {
                continue;
            }

            foreach ($provider->dependencies() as $dependency) {
                $dep = $bySlug[$dependency] ?? null;
                if ($dep === null) {
                    $missing[] = [
                        'provider' => $provider->slug(),
                        'dependency' => $dependency,
                        'reason' => 'No registered feature provides this slug.',
                    ];
                } elseif (! $dep->enabled()) {
                    $missing[] = [
                        'provider' => $provider->slug(),
                        'dependency' => $dependency,
                        'reason' => "The dependency [{$dependency}] is disabled.",
                    ];
                }
            }
        }

        return $missing;
    }

    public function getAllNavItems(): array
    {
        return $this->remember('nav_items', function () {
            $items = [];

            foreach ($this->enabledProviders() as $provider) {
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
        });
    }

    public function getAllSharedData(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getSharedData(), $this->enabledProviders())
        );
    }

    public function getAllDashboardData(): array
    {
        $data = [];

        foreach ($this->enabledProviders() as $provider) {
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

        foreach ($this->enabledProviders() as $provider) {
            $series = array_merge($series, $provider->getAnalyticsSeries());
        }

        return $series;
    }

    public function getAllNotificationTypes(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getNotificationTypes(), $this->enabledProviders())
        );
    }

    /**
     * Collect sitemap URL entries from every enabled feature provider. The
     * SitemapController prefixes these relative paths with the site base URL.
     *
     * @return array<int, array{loc: string, changefreq: string, priority: string, lastmod?: string|null}>
     */
    public function getAllSitemapUrls(): array
    {
        $urls = [];

        foreach ($this->enabledProviders() as $provider) {
            foreach ($provider->getSitemapUrls() as $entry) {
                $urls[] = $entry;
            }
        }

        return $urls;
    }

    public function getAllEmailTemplates(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getEmailTemplates(), $this->enabledProviders())
        );
    }

    public function getAllOrphanMediaTargets(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getOrphanMediaTargets(), $this->enabledProviders())
        );
    }

    public function getAllSettingsGroups(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getSettingsGroups(), $this->enabledProviders())
        );
    }

    public function getAllGalleryConfigs(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getGalleryConfig(), $this->enabledProviders())
        );
    }

    public function getAllMetaFormattingConfigs(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getMetaFormattingConfig(), $this->enabledProviders())
        );
    }

    public function getAllRepeaterConfigs(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getRepeaterConfig(), $this->enabledProviders())
        );
    }

    public function getAllProfileFieldConfigs(): array
    {
        return array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getProfileFieldsConfig(), $this->enabledProviders())
        );
    }

    public function getAllProfileSections(): array
    {
        return $this->remember('profile_sections', fn () => array_values(array_unique(array_merge(
            ...array_map(fn (FeatureProvider $provider) => $provider->getProfileSections(), $this->enabledProviders())
        ))));
    }

    public function getAllContentDescriptions(): array
    {
        return $this->remember('content_descriptions', function () {
            $map = [];

            foreach ($this->enabledProviders() as $provider) {
                $description = $provider->getContentDescription();

                if ($description !== null && $description->getSlug() !== '') {
                    $map[$description->getSlug()] = $description;
                }
            }

            return $map;
        });
    }

    public function getAllMediaSubdirectories(): array
    {
        return $this->remember('media_subdirectories', function () {
            $map = [];

            foreach ($this->enabledProviders() as $provider) {
                $subdir = $provider->getMediaSubdirectory();

                if ($subdir !== null && $subdir !== '') {
                    $map[$provider->slug()] = $subdir;
                }
            }

            return $map;
        });
    }

    public function getAllHealthChecks(): array
    {
        $checks = [];

        foreach ($this->enabledProviders() as $provider) {
            foreach ($provider->getHealthChecks() as $name => $passed) {
                $checks[$provider->slug().':'.$name] = [
                    'provider' => $provider->slug(),
                    'check' => $name,
                    'passed' => $passed,
                ];
            }
        }

        return $checks;
    }

    public function getAllScheduledTasks(): array
    {
        $tasks = [];

        foreach ($this->enabledProviders() as $provider) {
            foreach ($provider->getScheduledTasks() as $task) {
                $task['provider'] = $provider->slug();
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    public function getAllSettingsSections(): array
    {
        return $this->remember('settings_sections', function () {
            $sections = [];

            foreach ($this->enabledProviders() as $provider) {
                $section = $provider->getSettingsSection();

                if ($section !== null) {
                    $sections[$provider->slug()] = $section;
                }
            }

            return $sections;
        });
    }

    public function getAllDeactivationNotices(): array
    {
        $notices = [];

        foreach ($this->allProviders() as $provider) {
            if ($provider->enabled()) {
                continue;
            }

            $notice = $provider->getDeactivationNotice();

            if ($notice !== null) {
                $notices[$provider->slug()] = $notice;
            }
        }

        return $notices;
    }

    public function getAllSettingsRoutes(): array
    {
        return $this->remember('settings_routes', fn () => array_filter(array_map(
            fn (FeatureProvider $provider) => $this->resolveSettingsUrl($provider->getSettingsRoute()),
            $this->enabledProviders(),
        )));
    }

    /**
     * Settings navigation entries for providers that expose a settings page,
     * ready to be rendered by the admin settings sidebar.
     *
     * @return array<int, array{label: string, route: string, url: string}>
     */
    public function getAllSettingsNav(): array
    {
        return $this->remember('settings_nav', function () {
            $nav = [];

            foreach ($this->enabledProviders() as $provider) {
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
        });
    }

    public function getAllHasSettings(): array
    {
        return $this->remember('has_settings', fn () => array_filter(array_map(
            fn (FeatureProvider $provider) => $provider->hasSettings(),
            $this->enabledProviders(),
        )));
    }

    public function permissions(): array
    {
        return $this->remember('permissions', function () {
            $permissions = [];

            foreach ($this->enabledProviders() as $provider) {
                $group = $provider->name();

                foreach ($provider->permissions() as $permission) {
                    if (is_array($permission) && ! isset($permission['group']) && $group !== null) {
                        $permission['group'] = $group;
                    }

                    $permissions[] = $permission;
                }
            }

            return $permissions;
        });
    }

    /**
     * @return array<int, FeatureProvider>
     */
    public function allProviders(): array
    {
        return array_values($this->providers);
    }

    /**
     * Metadata for EVERY registered feature (enabled or not), used by the
     * module-management admin page so admins can see and toggle features that
     * are currently off. Contrast with the aggregators, which only consider
     * enabled providers.
     */
    public function getModuleList(): array
    {
        return $this->remember('module_list', fn () => array_map(
            fn (FeatureProvider $provider) => [
                'slug' => $provider->slug(),
                'name' => $provider->name(),
                'description' => $provider->description(),
                'version' => $provider->version(),
                'package' => $provider->getComposerPackage(),
                'origin' => $this->resolveProviderOrigin($provider),
                'dependencies' => $provider->dependencies(),
                'enabled' => $provider->enabled(),
                'has_settings' => $provider->hasSettings(),
                'settings_route' => $this->resolveSettingsUrl($provider->getSettingsRoute()),
            ],
            array_values($this->providers),
        ));
    }

    /**
     * Classify where a feature's source lives so the system health page can
     * distinguish genuinely platform-bundled features from project-owned
     * features and standalone composer packages. A feature whose provider
     * resolves from packages/platform is a workspace copy of the platform
     * itself and still counts as bundled.
     */
    protected function resolveProviderOrigin(FeatureProvider $provider): string
    {
        $package = $provider->getComposerPackage();

        if ($package !== null && $package !== '') {
            return 'package';
        }

        try {
            $file = (new \ReflectionClass($provider))->getFileName() ?: '';
        } catch (\ReflectionException) {
            return 'unknown';
        }

        $file = str_replace('\\', '/', $file);
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        if (str_starts_with($file, $base.'vendor/nreljed/platform/')) {
            return 'platform';
        }

        if (str_starts_with($file, $base.'packages/platform/')) {
            return 'platform';
        }

        if (str_starts_with($file, $base.'vendor/')) {
            return 'package';
        }

        if (str_starts_with($file, $base.'packages/')) {
            return 'workspace';
        }

        if (str_starts_with($file, $base.'app/')) {
            return 'project';
        }

        return 'unknown';
    }

    /**
     * @deprecated Use getModuleList() — kept as an alias so existing callers
     *             get the full, correctly-flavoured metadata.
     */
    public function getAll(): array
    {
        return $this->getModuleList();
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
                } elseif ($provider->enabled() && ! $bySlug[$dependency]->enabled()) {
                    $issues[] = [
                        'severity' => 'high',
                        'provider' => $slug,
                        'message' => "Declares dependency [{$dependency}] which is currently disabled.",
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
