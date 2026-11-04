<?php

namespace Nreljed\Platform\Contracts\Capabilities;

use Nreljed\Platform\Support\SettingsSection;

/**
 * The feature has settings: either a dedicated settings page of its own, or a
 * declarative section rendered inside the shared admin settings page.
 */
interface HasSettings
{
    /**
     * Whether the feature exposes settings at all. A feature that returns false
     * is left out of the settings navigation entirely.
     */
    public function hasSettings(): bool;

    /**
     * Return the NAME of the feature's settings route (not a resolved URL), or
     * null when the feature has no settings page of its own. The registry
     * resolves the route lazily, so a stale or missing route name degrades to
     * "no settings link" instead of throwing.
     */
    public function getSettingsRoute(): ?string;

    /**
     * Names of additional settings groups this feature owns, e.g. `['shop']`.
     * Aggregated into SettingsService::groups() alongside the host
     * `core.settings.groups` config.
     *
     * @return array<int, string>
     */
    public function getSettingsGroups(): array;

    /**
     * A declarative settings-section definition for the unified admin settings
     * page, or null when the feature has no section. The SettingsSection value
     * object captures the tab, label, icon, Inertia component, submit URL and
     * nonce key so the settings page can render the feature's settings without
     * a dedicated route.
     */
    public function getSettingsSection(): ?SettingsSection;
}
