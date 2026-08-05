<?php

namespace Nreljed\DashboardCore\Concerns;

/**
 * Safe no-op defaults for the optional capability methods of the
 * FeatureProvider contract.
 *
 * Providers `use` this trait and override only the methods they support.
 * The identity methods `name()` and `slug()` are intentionally NOT provided
 * here so a feature without them fails to load.
 */
trait InteractsWithFeatures
{
    public function description(): ?string
    {
        return null;
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function getNavGroupLabel(): ?string
    {
        return null;
    }

    public function getNavItems(): array
    {
        return [];
    }

    public function getDashboardData(): array
    {
        return [];
    }

    public function getAnalyticsSeries(): array
    {
        return [];
    }

    public function getSharedData(): array
    {
        return [];
    }

    public function getSettingsRoute(): ?string
    {
        return null;
    }

    public function hasSettings(): bool
    {
        return false;
    }

    public function getMetaFormattingConfig(): array
    {
        return [];
    }

    public function getRepeaterConfig(): array
    {
        return [];
    }

    public function getGalleryConfig(): array
    {
        return [];
    }

    public function getProfileFieldsConfig(): array
    {
        return [];
    }

    public function getEmailTemplates(): array
    {
        return [];
    }

    public function getNotificationTypes(): array
    {
        return [];
    }

    public function permissions(): array
    {
        return [];
    }

    public function onUserDelete(int $userId): void {}
}
