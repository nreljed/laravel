<?php

namespace Nreljed\Platform\Support;

use Nreljed\Platform\Models\Permission;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Services\PermissionCacheService;

/**
 * Reconciles the config role catalog into the database.
 *
 * A consumer deploy runs `migrate` and never the seeder, so a permission or a
 * grant introduced by a new core release has no database presence until
 * somebody seeds by hand. `CheckPermission` reads an explicit slug list and
 * never passes through `Gate::before`, so an unprovisioned permission 403s its
 * route for everyone — admins included.
 *
 * The catalog's `'*'` is materialised into concrete `permission_role` rows,
 * which is why creating a permission alone is not enough: the role also needs
 * the grant. Conversely a `!slug` exclusion is a deliberate deny, so a role
 * that still carries that grant (normally because `'*'` was flattened before
 * the exclusion existed) loses it. Config wins; a permission granted by hand
 * to a role outside the catalog is never touched.
 *
 * Both the original provisioning migration and its follow-up sync migration
 * delegate here, so a fresh install and an already-migrated install reach the
 * same end state.
 */
final class RoleCatalogSynchroniser
{
    /**
     * @return array{created: int, granted: int, revoked: int}
     */
    public function sync(): array
    {
        $created = $this->createMissingPermissions();
        $granted = $this->grantMissingRolePermissions();
        $revoked = $this->revokeExcludedRolePermissions();

        if ($created > 0 || $granted > 0 || $revoked > 0) {
            app(PermissionCacheService::class)->flushAll();
        }

        return ['created' => $created, 'granted' => $granted, 'revoked' => $revoked];
    }

    /**
     * Insert a permission row for every catalog entry that has none yet.
     */
    private function createMissingPermissions(): int
    {
        $created = 0;

        foreach (RoleCatalog::permissions() as $permission) {
            $permission['description'] ??= null;

            if (Permission::where('slug', $permission['slug'])->exists()) {
                continue;
            }

            Permission::create([
                'slug' => $permission['slug'],
                'name' => $permission['name'],
                'description' => $permission['description'],
                'guard_name' => 'web',
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Grant every catalog permission a config role resolves to and does not
     * already hold.
     */
    private function grantMissingRolePermissions(): int
    {
        $granted = 0;

        foreach (RoleCatalog::roles() as $slug => $definition) {
            $role = Role::where('slug', $slug)->first();

            if (! $role) {
                continue;
            }

            $ids = $this->resolvePermissionIds($definition['permissions'] ?? []);

            if ($ids === []) {
                continue;
            }

            $existing = $role->permissions()->pluck('permissions.id')->all();

            $missing = array_values(array_diff($ids, $existing));

            if ($missing === []) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching($missing);
            $granted += count($missing);
        }

        return $granted;
    }

    /**
     * Enforce the catalog's explicit `!slug` denials. A role that still carries
     * an excluded permission loses that grant; everything else is left alone.
     */
    private function revokeExcludedRolePermissions(): int
    {
        $revoked = 0;

        foreach (RoleCatalog::roles() as $slug => $definition) {
            $role = Role::where('slug', $slug)->first();

            if (! $role) {
                continue;
            }

            $excludedSlugs = $this->excludedSlugs($definition['permissions'] ?? []);

            if ($excludedSlugs === []) {
                continue;
            }

            $ids = Permission::whereIn('slug', $excludedSlugs)->pluck('id')->all();

            if ($ids === []) {
                continue;
            }

            $revoked += $role->permissions()->detach($ids);
        }

        return $revoked;
    }

    /**
     * @return list<string>
     */
    private function excludedSlugs(array|string $permissions): array
    {
        $entries = is_array($permissions) ? $permissions : [$permissions];

        $excluded = [];

        foreach ($entries as $entry) {
            if (is_string($entry) && str_starts_with($entry, '!')) {
                $excluded[] = substr($entry, 1);
            }
        }

        return array_values(array_unique($excluded));
    }

    /**
     * Mirrors RolesAndPermissionsSeeder::resolvePermissionIds(): `'*'` grants
     * everything and a `!slug` entry removes it.
     *
     * @return list<int>
     */
    private function resolvePermissionIds(array|string $permissions): array
    {
        $entries = is_array($permissions) ? $permissions : [$permissions];

        $excludedSlugs = $this->excludedSlugs($permissions);

        $ids = in_array('*', $entries, true)
            ? Permission::pluck('id')->all()
            : Permission::whereIn('slug', array_values(array_filter(
                $entries,
                fn ($entry) => $entry !== '*' && ! str_starts_with((string) $entry, '!'),
            )))->pluck('id')->all();

        if ($excludedSlugs !== []) {
            $excludedIds = Permission::whereIn('slug', $excludedSlugs)->pluck('id')->all();
            $ids = array_values(array_diff($ids, $excludedIds));
        }

        return array_values(array_unique($ids));
    }
}
