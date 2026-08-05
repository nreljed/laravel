<?php

namespace Nreljed\DashboardCore\Contracts;

/**
 * Contract for self-contained feature providers discovered by the
 * FeatureRegistry.
 *
 * Implement this interface on a service provider and register the class in
 * the `core.features` config. A provider that does not implement the
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

    public function dependencies(): array;

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

    public function getEmailTemplates(): array;

    public function getNotificationTypes(): array;

    public function permissions(): array;

    public function onUserDelete(int $userId): void;
}
