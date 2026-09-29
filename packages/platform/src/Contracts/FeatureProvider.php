<?php

namespace Nreljed\Platform\Contracts;

use Nreljed\Platform\Models\User;
use Nreljed\Platform\Support\ContentDescription;
use Nreljed\Platform\Support\DataBackupCapability;
use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\Platform\Support\SettingsSection;

/**
 * Contract for self-contained feature providers discovered by the
 * FeatureRegistry.
 *
 * Implement this interface on a service provider and register the class in
 * the `features.providers` config. A provider that does not implement the
 * interface fails loudly at boot instead of breaking at request time.
 *
 * Providers should combine this interface with the
 * InteractsWithFeatures trait, which supplies safe no-op defaults for every
 * capability method, and then override the methods they actually support.
 *
 * The only methods a provider must implement itself are `name()` and
 * `slug()`.
 */
interface FeatureProvider
{
    public function name(): string;

    public function slug(): string;

    public function description(): ?string;

    public function version(): string;

    /**
     * Return the fully-qualified Composer package this feature ships as (e.g.
     * "nreljed/shop-module"), or null when bundled inside core. Optional —
     * InteractsWithFeatures provides a null default. Used to resolve the real
     * installed version for the system-health page.
     */
    public function getComposerPackage(): ?string;

    public function dependencies(): array;

    /**
     * Whether this feature is currently enabled.
     *
     * Two-level resolution (highest precedence first), mirrored from the
     * platform module system:
     *  1. Persisted override (settings table, group `core`, key
     *     `feature_enabled.<slug>`) — written by the module-management admin
     *     page and the toggle endpoint.
     *  2. Config default (`features.enabled.<slug>`) — a feature listed
     *     in `features.providers` is enabled unless this config turns it off.
     *
     * A feature that is disabled contributes no routes (its provider gates
     * route loading on this), no nav, no dashboard data, no settings and no
     * permissions to the FeatureRegistry aggregators.
     */
    public function enabled(): bool;

    /**
     * Called when the feature transitions from enabled to disabled through
     * the module-management admin page.
     *
     * Implementations should clean up any state that must not survive while
     * the feature is off (e.g. de-prioritise jobs, disable listeners). The
     * default is a no-op; providers opt into cleanup. Data is intentionally
     * NOT destroyed so the feature can be re-enabled non-destructively.
     */
    public function onDisable(): void;

    public function getNavGroupLabel(): ?string;

    public function getNavItems(): array;

    public function getDashboardData(): array;

    public function getAnalyticsSeries(): array;

    public function getSharedData(): array;

    /**
     * Return the NAME of the feature's settings route (not a resolved URL),
     * or null when the feature has no settings page. The registry resolves
     * the route lazily, so a stale or missing route name degrades to "no
     * settings link" instead of throwing.
     */
    public function getSettingsRoute(): ?string;

    public function hasSettings(): bool;

    public function getMetaFormattingConfig(): array;

    public function getRepeaterConfig(): array;

    public function getGalleryConfig(): array;

    public function getProfileFieldsConfig(): array;

    /**
     * Return a list of profile section keys that this feature wants to opt
     * into on the `/dashboard/account/profile` page.
     *
     * The core profile page renders a slot that resolves each returned key to
     * a core React section component. This lets features extend the account
     * area (e.g. `['address']` for shop billing/shipping) WITHOUT overriding
     * the whole profile page component.
     */
    public function getProfileSections(): array;

    public function getEmailTemplates(): array;

    public function getNotificationTypes(): array;

    /**
     * Return a map of storage folder => model class that this feature wants
     * scanned by the `core:cleanup-orphan-media` command, e.g.
     * `['products' => Product::class]`. Aggregated alongside the host
     * `media.orphan_media` config.
     *
     * @return array<string, class-string>
     */
    public function getOrphanMediaTargets(): array;

    /**
     * Return the names of additional settings groups this feature owns, e.g.
     * `['shop']`. Aggregated into SettingsService::groups() alongside the
     * host `core.settings.groups` config.
     *
     * @return array<int, string>
     */
    public function getSettingsGroups(): array;

    public function permissions(): array;

    /**
     * Return sitemap URL entries this feature wants included in the shared
     * `/sitemap.xml`. Each entry is a map with `loc`, `changefreq`, `priority`
     * and optional `lastmod` (an ISO date string), matching the base
     * SitemapController's `url()` shape. The controller prefixes each `loc`
     * with the site base URL, so return a relative path (e.g. `/store`,
     * `/store/my-product`).
     *
     * @return array<int, array{loc: string, changefreq: string, priority: string, lastmod?: string|null}>
     */
    public function getSitemapUrls(): array;

    public function onUserDelete(int $userId): void;

    /**
     * Return the storage subdirectory this feature uses for media uploads,
     * e.g. "posts" or "products". Used by the shared media library to scope
     * queries and by the orphan scanner to reconcile disk contents.
     *
     * Return null when the feature does not upload media (e.g. AI, PublicSite).
     */
    public function getMediaSubdirectory(): ?string;

    /**
     * Return a declarative content-type description that the shared content
     * pipeline can consume, or null when the feature does not expose a
     * content type. The ContentDescription value object captures the model
     * class, label, feature flags, taxonomy declarations, SEO meta config,
     * and media settings so a single generic pipeline can serve CRUD,
     * editor, upload, taxonomy, and notification wiring.
     */
    public function getContentDescription(): ?ContentDescription;

    /**
     * Return a map of health-check names to booleans. The system-health
     * page aggregates these from every enabled provider so admins can see
     * per-feature runtime probes (e.g. "posts_table" => true).
     *
     * @return array<string, bool>
     */
    public function getHealthChecks(): array;

    /**
     * Return the scheduled tasks this feature registers. Each entry is an
     * associative array with at least "command" (the artisan class) and
     * "frequency" (e.g. "daily", "hourly", "every:60"). Used by the
     * system-health page and enables automatic unscheduling on disable.
     *
     * @return array<int, array{command: string, frequency: string, description?: string}>
     */
    public function getScheduledTasks(): array;

    /**
     * Return a declarative settings-section definition for the unified
     * admin settings page, or null when the feature has no settings. The
     * SettingsSection value object captures the tab, label, icon, Inertia
     * component, submit URL, and nonce key so the settings page can render
     * the feature's settings without a dedicated route.
     */
    public function getSettingsSection(): ?SettingsSection;

    /**
     * Return the declarative admin resources this feature exposes, e.g. its
     * TaxRates, Currencies and Products. Each entry is a
     * `Nreljed\Platform\Support\ResourceDefinition` and the dashboard route
     * that renders it; the declarative schema and runtime themselves live in
     * the frontend (`resources/js/lib/resource-schema.ts`), so this method
     * returns descriptors, not components.
     *
     * Named `getAdminResources` rather than the `adminResources` of the plan
     * doc to match every other accessor on this interface.
     *
     * Aggregated by FeatureRegistry::getAllAdminResources() so a consumer can
     * discover what a feature package offers without knowing its internals.
     * Scaffold one with `php artisan make:resource`.
     *
     * @return array<int, ResourceDefinition>
     */
    public function getAdminResources(): array;

    /**
     * Return an HTML notice displayed to admins on the module-management
     * page when this feature is disabled. Useful for explaining what
     * functionality is unavailable. Return null for no notice.
     */
    public function getDeactivationNotice(): ?string;

    /**
     * Return the data backup capabilities this feature exposes to the account
     * "Data" area. Each capability declares one module the user can back up
     * and/or restore, e.g. `new DataBackupCapability('posts', 'Posts, categories and tags')`.
     *
     * The account Data page aggregates these from every enabled provider so
     * users can see exactly which modules support backup and which support
     * restore. Behaviour lives in exportDataBackup() / importDataBackup().
     *
     * @return array<int, DataBackupCapability>
     */
    public function getDataBackupCapabilities(): array;

    /**
     * Build the user-owned data payload for the given capability key, ready
     * to be serialized into a downloadable backup file.
     *
     * Implementations MUST scope strictly to the authenticated user's own
     * records so a backup only ever contains that user's data.
     *
     * @return array<string, mixed> data payload (usually a list of records)
     */
    public function exportDataBackup(string $key, User $user): array;

    /**
     * Replace this user's data for the given capability key with the
     * contents of a previously exported backup file. The provider decides
     * what "replace" means for its records (e.g. restore owned rows, delete
     * owned rows absent from the file). Shared/non-owned resources must not
     * be deleted.
     *
     * @return int number of records restored/imported
     */
    public function importDataBackup(string $key, User $user, array $data): int;

    /**
     * Count of the user's own records in the given capability's module,
     * shown on the account Data page ("N records").
     */
    public function dataBackupSummary(string $key, User $user): int;
}
