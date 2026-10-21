<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Nreljed\Platform\Models\Permission;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Services\PermissionCacheService;
use Nreljed\Platform\Support\RoleCatalog;

/**
 * Provision every permission in the config catalog into the database.
 *
 * A consumer deploy runs `migrate` and never the seeder, so a permission
 * introduced by a new core release has no row until someone seeds by hand.
 * Because `CheckPermission` reads an explicit slug list (it never passes
 * through `Gate::before`, so admins get no wildcard bypass) that leaves the
 * new route 403 for everyone — including admins.
 *
 * The grants are materialised rather than resolved at runtime: the catalog's
 * `'*'` is flattened into concrete `permission_role` rows by the seeder, so
 * creating the permission alone would still leave every role unable to see it.
 *
 * This is deliberately additive. Missing rows and missing grants are created;
 * nothing an operator granted by hand is ever detached. Removing a permission
 * from config stays the seeder's job.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $created = $this->createMissingPermissions();
        $granted = $this->grantMissingRolePermissions();

        if ($created > 0 || $granted > 0) {
            app(PermissionCacheService::class)->flushAll();
        }
    }

    public function down(): void
    {
        // Additive backfill. Reversing it would delete permissions that may
        // have been granted to real users, so the catalog simply re-syncs.
    }

    protected function createMissingPermissions(): int
    {
        $created = 0;

        foreach (RoleCatalog::permissions() as $permission) {
            $permission['description'] ??= null;

            $exists = Permission::where('slug', $permission['slug'])->exists();

            if ($exists) {
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

    protected function grantMissingRolePermissions(): int
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
     * Mirrors RolesAndPermissionsSeeder::resolvePermissionIds(): `'*'` grants
     * everything and a `!slug` entry removes it.
     *
     * @return list<int>
     */
    protected function resolvePermissionIds(array|string $permissions): array
    {
        $entries = is_array($permissions) ? $permissions : [$permissions];

        $excludedSlugs = [];

        foreach ($entries as $entry) {
            if (is_string($entry) && str_starts_with($entry, '!')) {
                $excludedSlugs[] = substr($entry, 1);
            }
        }

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
};
