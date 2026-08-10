<?php

namespace Nreljed\DashboardCore\Support;

use Nreljed\DashboardCore\Services\FeatureRegistry;

/**
 * Merged view of the roles/permissions catalog.
 *
 * The engine ships base catalogs (config('roles.base_permissions') and
 * config('roles.base_roles')) plus feature-declared permissions. Consuming
 * applications extend them through config('roles.permissions') /
 * config('roles.roles') deltas. This helper is the single source of truth
 * for the merged result so the seeder, the permission UI, and role checks
 * can never drift from each other.
 */
final class RoleCatalog
{
    /**
     * All permissions: base catalog + app delta + every feature provider.
     * App entries win over base entries for the same slug.
     *
     * @return list<array{name: string, slug: string, description?: string, group?: string}>
     */
    public static function permissions(): array
    {
        $merged = [];

        foreach ([
            config('roles.base_permissions', []),
            config('roles.permissions', []),
            app(FeatureRegistry::class)->permissions(),
        ] as $catalog) {
            foreach ($catalog as $permission) {
                if (! is_array($permission) || ! isset($permission['slug'])) {
                    continue;
                }

                $merged[$permission['slug']] = $permission;
            }
        }

        return array_values($merged);
    }

    /**
     * All roles: base catalog merged with the app delta. App entries win for
     * the same slug; base-only roles are inherited unchanged.
     *
     * @return array<string, array{name: string, description?: string, sort_order?: int, permissions: mixed}>
     */
    public static function roles(): array
    {
        return array_replace(
            config('roles.base_roles', []),
            config('roles.roles', []),
        );
    }

    /**
     * Whether the given role slug exists in the merged catalog.
     */
    public static function hasRole(string $slug): bool
    {
        return array_key_exists($slug, self::roles());
    }
}
