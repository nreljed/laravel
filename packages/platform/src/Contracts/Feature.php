<?php

namespace Nreljed\Platform\Contracts;

/**
 * The minimal contract every feature must satisfy.
 *
 * A feature is a self-contained unit that plugs into the platform: it has an
 * identity (`name`, `slug`), a lifecycle (`enabled`, `onDisable`,
 * `onUserDelete`) and provenance metadata (`version`, `getComposerPackage`,
 * `dependencies`).
 *
 * Everything else is an OPT-IN capability declared by implementing one of the
 * interfaces in `Nreljed\Platform\Contracts\Capabilities`. A feature that
 * implements only this interface and `HasNavigation` contributes a sidebar
 * entry and nothing else — no permissions, no settings, no dashboard data.
 *
 * `FeatureProvider` is the pre-0.3 composite of this interface plus every
 * capability; it is retained for backwards compatibility and slated for
 * removal in v1.0.0.
 */
interface Feature
{
    public function name(): string;

    public function slug(): string;

    public function description(): ?string;

    public function version(): string;

    /**
     * Return the fully-qualified Composer package this feature ships as (e.g.
     * "nreljed/shop-module"), or null when bundled inside core. Used to resolve
     * the real installed version for the system-health page.
     */
    public function getComposerPackage(): ?string;

    /**
     * Slugs of the features this one requires. The registry reports a missing
     * or disabled dependency as a configuration issue instead of failing at
     * request time.
     *
     * @return list<string>
     */
    public function dependencies(): array;

    /**
     * Whether this feature is currently enabled.
     *
     * Two-level resolution (highest precedence first):
     *  1. Persisted override (settings table, group `core`, key
     *     `feature_enabled.<slug>`) — written by the module-management admin
     *     page and the toggle endpoint.
     *  2. Config default (`features.enabled.<slug>`) — a feature listed in
     *     `features.providers` is enabled unless this config turns it off.
     *
     * A disabled feature contributes no routes, nav, dashboard data, settings
     * or permissions to the registry aggregators.
     */
    public function enabled(): bool;

    /**
     * Called when the feature transitions from enabled to disabled through the
     * module-management admin page.
     *
     * Implementations should clean up state that must not survive while the
     * feature is off (e.g. de-prioritise jobs, disable listeners). Data is
     * intentionally NOT destroyed so the feature can be re-enabled
     * non-destructively.
     */
    public function onDisable(): void;

    /**
     * Called when a user is deleted so the feature can remove or anonymise the
     * records it owns for that user.
     */
    public function onUserDelete(int $userId): void;
}
