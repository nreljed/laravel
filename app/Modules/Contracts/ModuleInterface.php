<?php

namespace App\Modules\Contracts;

interface ModuleInterface
{
    public function name(): string;

    public function slug(): string;

    public function description(): ?string;

    public function version(): string;

    public function dependencies(): array;

    public function routes(): void;

    public function boot(): void;

    public function register(): void;

    public function getNavItems(): array;

    public function getMetaFormattingConfig(): array;

    public function getDashboardData(): array;

    public function getSharedData(): array;

    public function getSettingsRoute(): ?string;

    public function getFrontendPages(): array;

    public function migrationPaths(): array;

    public function permissions(): array;

    public function hasSettings(): bool;

    public function getRepeaterConfig(): array;

    public function getGalleryConfig(): array;

    public function getProfileFieldsConfig(): array;

    public function getEmailTemplates(): array;

    public function getNotificationTypes(): array;

    public function onUserDelete(int $userId): void;
}
