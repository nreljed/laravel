<?php

namespace Nreljed\Platform\Services;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nreljed\Platform\Contracts\Capabilities\HasAdminResources;
use Nreljed\Platform\Contracts\Capabilities\HasContentEditor;
use Nreljed\Platform\Contracts\Capabilities\HasContentType;
use Nreljed\Platform\Contracts\Capabilities\HasDashboardWidgets;
use Nreljed\Platform\Contracts\Capabilities\HasDataBackup;
use Nreljed\Platform\Contracts\Capabilities\HasDeactivation;
use Nreljed\Platform\Contracts\Capabilities\HasHealthChecks;
use Nreljed\Platform\Contracts\Capabilities\HasMedia;
use Nreljed\Platform\Contracts\Capabilities\HasNavigation;
use Nreljed\Platform\Contracts\Capabilities\HasNotifications;
use Nreljed\Platform\Contracts\Capabilities\HasPermissions;
use Nreljed\Platform\Contracts\Capabilities\HasProfileExtension;
use Nreljed\Platform\Contracts\Capabilities\HasSettings;
use Nreljed\Platform\Contracts\Capabilities\HasSharedData;
use Nreljed\Platform\Contracts\Capabilities\HasSitemap;
use Nreljed\Platform\Contracts\Feature;
use Nreljed\Platform\Contracts\FeatureProvider;
use Nreljed\Platform\Support\ContentDescription;
use Nreljed\Platform\Support\DataBackupCapability;
use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\Platform\Support\SettingsSection;

class FeatureRegistry
{
    /**
     * Icon used when a nav item reaches the sidebar without one.
     *
     * Every top-level nav entry is rendered as an icon plus a title, so an
     * item that arrives without an icon would render as a blank row.
     */
    public const string DEFAULT_NAV_ICON = 'FileText';

    /** @var array<string, Feature> */
    protected array $providers = [];

    /** @var array<string, mixed> */
    protected array $memoized = [];

    /** @var array<string, string> */
    protected array $adminResourceOwners = [];

    public function __construct()
    {
        foreach ($this->configuredProviders() as $class) {
            $this->providers[$class] = $this->getProviderOrFail($class);
        }
    }

    /**
     * Resolve one configured provider, or fail loudly.
     *
     * The registry only requires the minimal {@see Feature} contract. Anything
     * beyond it is opt-in, and every aggregator below dispatches on the
     * capability it actually needs — so a minimal feature contributes exactly
     * what it declared and nothing else.
     *
     * @param  class-string<ServiceProvider>  $class
     */
    protected function getProviderOrFail(string $class): Feature
    {
        $provider = app()->getProvider($class);

        if ($provider === null) {
            throw new \RuntimeException("Service provider [{$class}] is not registered.");
        }

        if (! $provider instanceof Feature) {
            throw new \RuntimeException(
                "Service provider [{$class}] must implement ".Feature::class.' '
                .'(or the deprecated '.FeatureProvider::class.' composite).'
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
     * @return list<Feature>
     */
    protected function enabledProviders(): array
    {
        return array_values(array_filter(
            $this->providers,
            fn (Feature $provider) => $provider->enabled()
        ));
    }

    // -----------------------------------------------------------------
    // Capability dispatch
    //
    // A feature declares what it offers by implementing an interface from
    // Nreljed\Platform\Contracts\Capabilities. Each accessor below is the
    // single place the registry learns that capability's absence default,
    // which keeps every aggregator free of instanceof branches.
    // -----------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    protected function navItems(Feature $feature): array
    {
        return $feature instanceof HasNavigation ? $feature->getNavItems() : [];
    }

    protected function navGroupLabel(Feature $feature): ?string
    {
        return $feature instanceof HasNavigation ? $feature->getNavGroupLabel() : null;
    }

    /**
     * @return array<int, array<string, mixed>|string>
     */
    protected function permissionsOf(Feature $feature): array
    {
        return $feature instanceof HasPermissions ? $feature->permissions() : [];
    }

    protected function hasSettings(Feature $feature): bool
    {
        return $feature instanceof HasSettings && $feature->hasSettings();
    }

    protected function settingsRoute(Feature $feature): ?string
    {
        return $feature instanceof HasSettings ? $feature->getSettingsRoute() : null;
    }

    /**
     * @return array<int, string>
     */
    protected function settingsGroups(Feature $feature): array
    {
        return $feature instanceof HasSettings ? $feature->getSettingsGroups() : [];
    }

    protected function settingsSection(Feature $feature): ?SettingsSection
    {
        return $feature instanceof HasSettings ? $feature->getSettingsSection() : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function dashboardData(Feature $feature): array
    {
        return $feature instanceof HasDashboardWidgets ? $feature->getDashboardData() : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function analyticsSeries(Feature $feature): array
    {
        return $feature instanceof HasDashboardWidgets ? $feature->getAnalyticsSeries() : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function notificationTypes(Feature $feature): array
    {
        return $feature instanceof HasNotifications ? $feature->getNotificationTypes() : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emailTemplates(Feature $feature): array
    {
        return $feature instanceof HasNotifications ? $feature->getEmailTemplates() : [];
    }

    protected function mediaSubdirectory(Feature $feature): ?string
    {
        return $feature instanceof HasMedia ? $feature->getMediaSubdirectory() : null;
    }

    /**
     * @return array<string, class-string>
     */
    protected function orphanMediaTargets(Feature $feature): array
    {
        return $feature instanceof HasMedia ? $feature->getOrphanMediaTargets() : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function galleryConfig(Feature $feature): array
    {
        return $feature instanceof HasMedia ? $feature->getGalleryConfig() : [];
    }

    /**
     * @return array<int, array{loc: string, changefreq: string, priority: string, lastmod?: string|null}>
     */
    protected function sitemapUrls(Feature $feature): array
    {
        return $feature instanceof HasSitemap ? $feature->getSitemapUrls() : [];
    }

    /**
     * @return array<string, bool>
     */
    protected function healthChecks(Feature $feature): array
    {
        return $feature instanceof HasHealthChecks ? $feature->getHealthChecks() : [];
    }

    /**
     * @return array<int, array{command: string, frequency: string, description?: string}>
     */
    protected function scheduledTasks(Feature $feature): array
    {
        return $feature instanceof HasHealthChecks ? $feature->getScheduledTasks() : [];
    }

    /**
     * @return array<int, ResourceDefinition>
     */
    protected function adminResources(Feature $feature): array
    {
        return $feature instanceof HasAdminResources ? $feature->getAdminResources() : [];
    }

    protected function contentDescription(Feature $feature): ?ContentDescription
    {
        return $feature instanceof HasContentType ? $feature->getContentDescription() : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function metaFormattingConfig(Feature $feature): array
    {
        return $feature instanceof HasContentEditor ? $feature->getMetaFormattingConfig() : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function repeaterConfig(Feature $feature): array
    {
        return $feature instanceof HasContentEditor ? $feature->getRepeaterConfig() : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function profileFieldsConfig(Feature $feature): array
    {
        return $feature instanceof HasProfileExtension ? $feature->getProfileFieldsConfig() : [];
    }

    /**
     * @return list<string>
     */
    protected function profileSections(Feature $feature): array
    {
        return $feature instanceof HasProfileExtension ? $feature->getProfileSections() : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedData(Feature $feature): array
    {
        return $feature instanceof HasSharedData ? $feature->getSharedData() : [];
    }

    /**
     * @return array<int, DataBackupCapability>
     */
    protected function dataBackupCapabilities(Feature $feature): array
    {
        return $feature instanceof HasDataBackup ? $feature->getDataBackupCapabilities() : [];
    }

    protected function deactivationNotice(Feature $feature): ?string
    {
        return $feature instanceof HasDeactivation ? $feature->getDeactivationNotice() : null;
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

            // Descriptors are grouped by owning feature slug up front so each
            // provider can adopt the resources it declared.
            $descriptorsByOwner = [];

            foreach ($this->getAllAdminResources() as $descriptor) {
                $owner = $this->getAdminResourceOwners()[$descriptor->getKey()] ?? null;

                if ($owner !== null) {
                    $descriptorsByOwner[$owner][] = $descriptor;
                }
            }

            foreach ($this->enabledProviders() as $provider) {
                $groupLabel = $this->navGroupLabel($provider);
                $providerItems = [];

                foreach ($this->navItems($provider) as $item) {
                    // Normalize group labels so the sidebar renders consistent
                    // headings even when a provider only exposes getNavGroupLabel().
                    if ($groupLabel !== null && ! isset($item['groupLabel'])) {
                        $item['groupLabel'] = $groupLabel;
                    }

                    $providerItems[] = $item;
                }

                // Build one sidebar item per admin resource that declares an index
                // route. The generic kernel pages (dashboard/admin/resources/*)
                // render the payload; the provider's own route + controller stay
                // authoritative. Only resources with a resolvable index route get
                // an item — a descriptor awaiting its routes stays invisible.
                $resourceItems = [];

                foreach ($descriptorsByOwner[$provider->slug()] ?? [] as $descriptor) {
                    $indexRoute = $descriptor->getRoutes()['index'] ?? null;

                    if (! is_string($indexRoute) || $indexRoute === '' || ! Route::has($indexRoute)) {
                        continue;
                    }

                    $permissions = $descriptor->getPermissions();

                    $resourceItems[] = array_filter([
                        'title' => $descriptor->getLabel(),
                        'url' => route($indexRoute),
                        'feature_slug' => $provider->slug(),
                        'permission' => $permissions['view'] ?? null,
                    ], fn ($value) => $value !== null);
                }

                if ($resourceItems === []) {
                    $items = [...$items, ...array_map($this->withNavIcon(...), $providerItems)];

                    continue;
                }

                // Adopt the resources into the provider's own collapsible group so
                // the sidebar shows one heading per module. A provider that returns
                // exactly one collapsible entry owns it; anything else falls back to
                // flat emission, which keeps the contract safe for providers without
                // a group to nest into.
                $groupIndex = $this->collapsibleGroupIndex($providerItems);

                if ($groupIndex === null) {
                    $items = [...$items, ...array_map($this->withNavIcon(...), $providerItems)];

                    // No group to nest into, so the resources need a heading of their own.
                    foreach ($resourceItems as $resourceItem) {
                        $items[] = $this->withNavIcon(['groupLabel' => 'Resources', ...$resourceItem]);
                    }

                    continue;
                }

                foreach ($providerItems as $index => $item) {
                    $items[] = $this->withNavIcon(
                        $index === $groupIndex
                            ? $this->withResourceSubItems($item, $resourceItems)
                            : $item
                    );
                }
            }

            return $items;
        });
    }

    /**
     * Guarantee a nav item carries an icon.
     *
     * A top-level nav entry is rendered as an icon plus a title, so an item
     * that arrives without one would render as a blank row. Providers that
     * declare their own icon keep it; everything else falls back to the
     * generic file icon.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function withNavIcon(array $item): array
    {
        $icon = $item['icon'] ?? null;

        if (! is_string($icon) || $icon === '') {
            $item['icon'] = self::DEFAULT_NAV_ICON;
        }

        return $item;
    }

    /**
     * Resolve the index of the single collapsible group a provider returned.
     *
     * A collapsible entry is one that carries its own `items` array. Only an
     * unambiguous single group is adopted; zero or several mean the provider has
     * no obvious place to nest resources into.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function collapsibleGroupIndex(array $items): ?int
    {
        $indexes = [];

        foreach ($items as $index => $item) {
            if ($this->isCollapsible($item)) {
                $indexes[] = $index;
            }
        }

        return count($indexes) === 1 ? $indexes[0] : null;
    }

    /**
     * Determine whether a nav item carries its own sub-item list.
     *
     * @param  array<string, mixed>  $item
     */
    protected function isCollapsible(array $item): bool
    {
        return isset($item['items']) && is_array($item['items']);
    }

    /**
     * Reduce a URL to a comparable path so relative and absolute forms of the
     * same link are recognised as duplicates.
     *
     * Modules write nav links with `route($name, [], false)` while the registry
     * generates absolute ones; comparing the raw strings would let the same
     * destination through twice.
     *
     * @param  mixed  $url
     */
    protected function normalizeUrl($url): string
    {
        if (! is_string($url) || $url === '') {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : $url;
    }

    /**
     * Append descriptor sub-items to a collapsible nav group, skipping any
     * descriptor whose URL a hand-written entry already covers.
     *
     * The hand-written entry is authoritative: modules declare curated links
     * (quick actions, module landing pages) that descriptors cannot express, so
     * an existing URL wins over the generated duplicate.
     *
     * @param  array<string, mixed>  $item
     * @param  array<int, array<string, mixed>>  $resourceItems
     * @return array<string, mixed>
     */
    protected function withResourceSubItems(array $item, array $resourceItems): array
    {
        $existingUrls = [];

        foreach ($item['items'] as $subItem) {
            if (is_array($subItem) && isset($subItem['url'])) {
                $existingUrls[] = $this->normalizeUrl($subItem['url']);
            }
        }

        $appended = [];

        foreach ($resourceItems as $resourceItem) {
            if (in_array($this->normalizeUrl($resourceItem['url']), $existingUrls, true)) {
                continue;
            }

            $appended[] = $resourceItem;
        }

        if ($appended === []) {
            return $item;
        }

        // Nested sub-items inherit the group's heading; carrying a stale
        // `groupLabel` on a sub-item would override it when rendered.
        foreach ($appended as $index => $resourceItem) {
            unset($appended[$index]['groupLabel']);
        }

        $item['items'] = [...array_values($item['items']), ...$appended];

        return $item;
    }

    public function getAllSharedData(): array
    {
        return array_merge(
            ...array_map($this->sharedData(...), $this->enabledProviders())
        );
    }

    public function getAllDashboardData(): array
    {
        $data = [];

        foreach ($this->enabledProviders() as $provider) {
            $featureData = $this->dashboardData($provider);

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
            $series = array_merge($series, $this->analyticsSeries($provider));
        }

        return $series;
    }

    public function getAllNotificationTypes(): array
    {
        return array_merge(
            ...array_map($this->notificationTypes(...), $this->enabledProviders())
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
            foreach ($this->sitemapUrls($provider) as $entry) {
                $urls[] = $entry;
            }
        }

        return $urls;
    }

    public function getAllEmailTemplates(): array
    {
        return array_merge(
            ...array_map($this->emailTemplates(...), $this->enabledProviders())
        );
    }

    public function getAllOrphanMediaTargets(): array
    {
        return array_merge(
            ...array_map($this->orphanMediaTargets(...), $this->enabledProviders())
        );
    }

    public function getAllSettingsGroups(): array
    {
        return array_merge(
            ...array_map($this->settingsGroups(...), $this->enabledProviders())
        );
    }

    /**
     * Every declarative admin resource exposed by the enabled features.
     *
     * Memoized because the descriptors are config-shaped - a resource's key,
     * label and route, with no per-user or live-DB state - which is the same
     * condition the `remember` docblock requires. A feature toggled at runtime
     * re-registers providers, so the cache is dropped with them.
     *
     * The kernel guarantee (plan 5.4): a descriptor that drifts off the
     * shipped schema (a misspelled column key, an unknown field type, a route
     * slot that is not a route name, a resource key colliding with another
     * feature) fails loudly here, so no module can silently fork the shared
     * admin surface. `assertValid()` carries the owning feature's slug so the
     * failure names the module that must fix it.
     *
     * @return array<int, ResourceDefinition>
     */
    public function getAllAdminResources(): array
    {
        return $this->remember('admin_resources', function () {
            $resources = [];
            $keys = [];

            foreach ($this->enabledProviders() as $provider) {
                foreach ($this->adminResources($provider) as $descriptor) {
                    if (! $descriptor instanceof ResourceDefinition) {
                        throw new \RuntimeException(
                            "Feature [{$provider->slug()}] returned a non-ResourceDefinition from getAdminResources(); "
                            .'the method must return descriptor objects, not raw arrays.'
                        );
                    }

                    $descriptor->assertValid($provider->slug());

                    $key = $descriptor->getKey();
                    if (isset($keys[$key])) {
                        throw new \RuntimeException(
                            "Duplicate admin resource key [{$key}] declared by features "
                            ."[{$keys[$key]}] and [{$provider->slug()}]."
                        );
                    }

                    $keys[$key] = $provider->slug();
                    $resources[] = $descriptor;
                }
            }

            $this->adminResourceOwners = $keys;

            return $resources;
        });
    }

    /**
     * Resolve a single validated descriptor by its globally unique key.
     */
    public function getAdminResource(string $key): ?ResourceDefinition
    {
        foreach ($this->getAllAdminResources() as $descriptor) {
            if ($descriptor->getKey() === $key) {
                return $descriptor;
            }
        }

        return null;
    }

    /**
     * Serialize every declared descriptor for the admin inventory page and
     * the shared Inertia prop. Keyed by resource key so the frontend can
     * address a resource without re-querying providers.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllAdminResourcesArray(): array
    {
        return $this->remember('admin_resources_array', function () {
            $resources = [];

            foreach ($this->getAllAdminResources() as $descriptor) {
                $resources[$descriptor->getKey()] = $descriptor->toArray();
            }

            return $resources;
        });
    }

    /**
     * Map resource key → owning feature slug. Populated while the validated
     * aggregate is built, so duplicate keys fail before a nav item is emitted.
     *
     * @return array<string, string>
     */
    public function getAdminResourceOwners(): array
    {
        $this->getAllAdminResources();

        return $this->adminResourceOwners;
    }

    public function getAllGalleryConfigs(): array
    {
        return array_merge(
            ...array_map($this->galleryConfig(...), $this->enabledProviders())
        );
    }

    public function getAllMetaFormattingConfigs(): array
    {
        return array_merge(
            ...array_map($this->metaFormattingConfig(...), $this->enabledProviders())
        );
    }

    public function getAllRepeaterConfigs(): array
    {
        return array_merge(
            ...array_map($this->repeaterConfig(...), $this->enabledProviders())
        );
    }

    public function getAllProfileFieldConfigs(): array
    {
        return array_merge(
            ...array_map($this->profileFieldsConfig(...), $this->enabledProviders())
        );
    }

    public function getAllProfileSections(): array
    {
        return $this->remember('profile_sections', fn () => array_values(array_unique(array_merge(
            ...array_map($this->profileSections(...), $this->enabledProviders())
        ))));
    }

    public function getAllContentDescriptions(): array
    {
        return $this->remember('content_descriptions', function () {
            $map = [];

            foreach ($this->enabledProviders() as $provider) {
                $description = $this->contentDescription($provider);

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
                $subdir = $this->mediaSubdirectory($provider);

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
            foreach ($this->healthChecks($provider) as $name => $passed) {
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
            foreach ($this->scheduledTasks($provider) as $task) {
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
                $section = $this->settingsSection($provider);

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

            $notice = $this->deactivationNotice($provider);

            if ($notice !== null) {
                $notices[$provider->slug()] = $notice;
            }
        }

        return $notices;
    }

    /**
     * Every data backup capability declared by enabled providers, paired with
     * its owning provider. The account "Data" page renders one entry per
     * capability, and the export/restore endpoints resolve by capability key.
     *
     * Keys are expected to be globally unique across enabled providers; a
     * duplicate key fails loudly so the module list can never be ambiguous.
     *
     * @return list<array{provider: Feature&HasDataBackup, capability: DataBackupCapability}>
     */
    public function getDataBackupModules(): array
    {
        return $this->remember('data_backup_modules', function () {
            $modules = [];

            foreach ($this->enabledProviders() as $provider) {
                foreach ($this->dataBackupCapabilities($provider) as $capability) {
                    $key = $capability->getKey();

                    if ($key === '') {
                        throw new \RuntimeException(
                            "Feature [{$provider->slug()}] declared a data backup capability with an empty key."
                        );
                    }

                    // Fail loudly on ambiguity instead of silently serving the
                    // wrong provider for export/restore.
                    foreach ($modules as $existing) {
                        if ($existing['capability']->getKey() === $key) {
                            throw new \RuntimeException(
                                "Duplicate data backup capability key [{$key}] declared by features "
                                ."[{$existing['provider']->slug()}] and [{$provider->slug()}]."
                            );
                        }
                    }

                    $modules[] = ['provider' => $provider, 'capability' => $capability];
                }
            }

            return $modules;
        });
    }

    /**
     * Resolve a data backup capability key to its provider + capability pair,
     * or null when no enabled provider declares it.
     *
     * @return array{provider: Feature&HasDataBackup, capability: DataBackupCapability}|null
     */
    public function resolveDataBackupModule(string $key): ?array
    {
        foreach ($this->getDataBackupModules() as $module) {
            if ($module['capability']->getKey() === $key) {
                return $module;
            }
        }

        return null;
    }

    public function getAllSettingsRoutes(): array
    {
        return $this->remember('settings_routes', fn () => array_filter(array_map(
            fn (Feature $provider) => $this->resolveSettingsUrl($this->settingsRoute($provider)),
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
                if (! $this->hasSettings($provider)) {
                    continue;
                }

                $route = $this->settingsRoute($provider);
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
            $this->hasSettings(...),
            $this->enabledProviders(),
        )));
    }

    public function permissions(): array
    {
        return $this->remember('permissions', function () {
            $permissions = [];

            foreach ($this->enabledProviders() as $provider) {
                $group = $provider->name();

                foreach ($this->permissionsOf($provider) as $permission) {
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
     * @return array<int, Feature>
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
            fn (Feature $provider) => [
                'slug' => $provider->slug(),
                'name' => $provider->name(),
                'description' => $provider->description(),
                'version' => $provider->version(),
                'package' => $provider->getComposerPackage(),
                'origin' => $this->resolveProviderOrigin($provider),
                'dependencies' => $provider->dependencies(),
                'enabled' => $provider->enabled(),
                'has_settings' => $this->hasSettings($provider),
                'settings_route' => $this->resolveSettingsUrl($this->settingsRoute($provider)),
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
    protected function resolveProviderOrigin(Feature $provider): string
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

        $file = $this->normalizePath($file);

        if ($file === '') {
            return 'unknown';
        }

        // A vendor directory installed through a symlink or a Windows junction
        // resolves to its real location, so the provider file no longer sits
        // under base_path(). Compare against the real path of the platform
        // package before falling back to the path-relative checks.
        foreach ($this->platformSourcePaths() as $platformPath) {
            if ($file === $platformPath || str_starts_with($file, $platformPath.'/')) {
                return 'platform';
            }
        }

        $base = rtrim($this->normalizePath(base_path()), '/').'/';

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
     * Real paths of every location the platform package can be loaded from.
     *
     * @return list<string>
     */
    protected function platformSourcePaths(): array
    {
        $paths = [];

        try {
            $installed = InstalledVersions::getInstallPath('nreljed/platform');

            if (is_string($installed)) {
                $paths[] = $installed;
            }
        } catch (\Throwable) {
            // The platform is not installed as a composer package in this
            // application - the workspace checks below still apply.
        }

        $paths[] = base_path('packages/platform');

        $normalized = [];

        foreach ($paths as $path) {
            $real = realpath($path);
            $normalized[] = rtrim($this->normalizePath($real !== false ? $real : $path), '/');
        }

        return array_values(array_unique(array_filter($normalized)));
    }

    /**
     * Normalise a filesystem path for prefix comparisons.
     */
    protected function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
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

            $settingsRoute = $this->settingsRoute($provider);
            if ($this->hasSettings($provider) && ($settingsRoute === null || $this->resolveSettingsUrl($settingsRoute) === null)) {
                $issues[] = [
                    'severity' => 'medium',
                    'provider' => $slug,
                    'message' => "Declares a settings page but route [{$settingsRoute}] cannot be resolved.",
                ];
            }

            $dashboardData = $this->dashboardData($provider);
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

            // Resource descriptors are validated loudly when the registry
            // aggregates them, but surface them here too so the system health
            // page reports the drift instead of leaving it to the next
            // dashboard render to 500 on.
            foreach ($this->adminResources($provider) as $descriptor) {
                if (! $descriptor instanceof ResourceDefinition) {
                    $issues[] = [
                        'severity' => 'critical',
                        'provider' => $slug,
                        'message' => 'getAdminResources() returned a raw array instead of a ResourceDefinition.',
                    ];

                    continue;
                }

                foreach ($descriptor->violations() as $violation) {
                    $issues[] = [
                        'severity' => 'high',
                        'provider' => $slug,
                        'message' => "Resource [{$descriptor->getKey()}]: {$violation}",
                    ];
                }
            }
        }

        // Resource keys must be globally unique across enabled features so the
        // registry lookup and the frontend inventory cannot be ambiguous.
        $descriptorKeys = [];
        foreach ($this->enabledProviders() as $provider) {
            foreach ($this->adminResources($provider) as $descriptor) {
                if (! $descriptor instanceof ResourceDefinition) {
                    continue;
                }

                $key = $descriptor->getKey();
                if (isset($descriptorKeys[$key]) && $descriptorKeys[$key] !== $provider->slug()) {
                    $issues[] = [
                        'severity' => 'critical',
                        'provider' => $provider->slug(),
                        'message' => "Duplicate admin resource key [{$key}] already declared by [{$descriptorKeys[$key]}].",
                    ];
                }

                $descriptorKeys[$key] = $provider->slug();
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
