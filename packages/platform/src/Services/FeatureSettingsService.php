<?php

namespace Nreljed\Platform\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Nreljed\Platform\Models\Setting;

/**
 * Persists and reads the per-feature enabled/disabled state introduced by the
 * module-management admin page.
 *
 * State lives in the `settings` table under the `core` group, keyed
 * `feature_enabled.<slug>` with a `"1"`/`"0"` value. This sits ABOVE the
 * `features.enabled.<slug>` config default so admins can enable/disable a
 * feature at runtime without touching config, and it can be reverted by
 * deleting the row.
 */
class FeatureSettingsService
{
    public const string GROUP = 'core';

    protected static function cacheKey(string $slug): string
    {
        return 'feature_enabled:'.$slug;
    }

    /**
     * Resolve whether the feature is enabled.
     *
     * Precedence:
     *  1. Persisted settings row (`feature_enabled.<slug>`) if present.
     *  2. The declared default (usually `features.enabled.<slug>`,
     *     falling back to enabled).
     *
     * When the settings table is unavailable (pre-migration, seeders, console)
     * this silently degrades to the default so the app never hard-fails on a
     * registry read.
     */
    public function isEnabled(string $slug, bool $default = true): bool
    {
        try {
            if (! Schema::hasTable((new Setting)->getTable())) {
                return $default;
            }

            $stored = Cache::remember(
                self::cacheKey($slug),
                300,
                fn () => Setting::where('key', self::key($slug))->value('value')
            );

            if ($stored === null) {
                return $default;
            }

            return $stored === '1' || $stored === 1 || $stored === true;
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Persist the enabled state for a feature. Returns the previous state
     * (or the default when no override existed).
     */
    public function setEnabled(string $slug, bool $enabled, bool $default = true): bool
    {
        $previous = $this->isEnabled($slug, $default);

        try {
            Setting::updateOrCreate(
                ['key' => self::key($slug)],
                ['value' => $enabled ? '1' : '0', 'group' => self::GROUP]
            );
        } catch (\Throwable) {
            // Settings table unavailable — nothing to persist.
        }

        Cache::forget(self::cacheKey($slug));
        try {
            app(SettingsService::class)->clearCache();
        } catch (\Throwable) {
        }

        return $previous;
    }

    /**
     * Remove a persisted override, reverting to the config default.
     */
    public function clear(string $slug): void
    {
        try {
            Setting::where('key', self::key($slug))->delete();
        } catch (\Throwable) {
        }

        Cache::forget(self::cacheKey($slug));
    }

    public static function key(string $slug): string
    {
        return 'feature_enabled.'.$slug;
    }
}
