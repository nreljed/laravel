<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Models\Permission;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Services\FeatureRegistry;
use Nreljed\Platform\Services\PermissionCacheService;
use Nreljed\Platform\Support\RoleCatalog;

trait BootsFeatures
{
    protected function bootFeatures(): void
    {
        $registry = app(FeatureRegistry::class);

        app(RolesAndPermissionsSeeder::class)->run();

        app('router')->getRoutes()->refreshNameLookups();

        // Collect permission slugs from all registered feature providers
        $featureSlugs = [];
        foreach ($registry->allProviders() as $provider) {
            foreach ($provider->permissions() as $perm) {
                $featureSlugs[] = $perm['slug'];
            }
        }

        // Create feature permissions in DB (not done by seeders)
        $existing = Permission::pluck('slug')->toArray();
        foreach ($featureSlugs as $slug) {
            if (! in_array($slug, $existing)) {
                Permission::create([
                    'name' => $slug,
                    'slug' => $slug,
                    'description' => '',
                    'guard_name' => 'web',
                ]);
                $existing[] = $slug;
            }
        }

        // Ensure manage_options permission exists
        if (! in_array('manage_options', $existing)) {
            Permission::create([
                'name' => 'Manage Options',
                'slug' => 'manage_options',
                'description' => 'Access application settings.',
                'guard_name' => 'web',
            ]);
        }

        $allPermIds = Permission::all()->pluck('id');
        $featurePermIds = Permission::whereIn('slug', $featureSlugs)->pluck('id');

        // Admin gets ALL permissions (production: Gate::before + permissions:sync)
        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole) {
            $adminRole->permissions()->sync($allPermIds);
        }

        // Shop Owner gets ALL feature permissions (matches production: permissions:sync)
        $shopOwnerRole = Role::where('slug', 'shop_owner')->first();
        if ($shopOwnerRole) {
            $existingIds = $shopOwnerRole->permissions()->pluck('permissions.id');
            $newIds = $featurePermIds->diff($existingIds)->values()->toArray();
            if (! empty($newIds)) {
                $shopOwnerRole->permissions()->attach($newIds);
            }
        }

        // Re-sync all other roles so that feature permissions created above
        // (from disabled providers) are properly linked. The seeder only
        // assigned permissions that existed at sync time; feature providers
        // that were fail-closed during seeding had no Permission rows yet.
        $this->resyncRolePermissions();

        $this->clearPermissionCache();
    }

    protected function createUserWithRole(string $roleSlug): mixed
    {
        $user = User::factory()->create();
        $user->assignRole($roleSlug);
        $this->clearUserPermissionCache($user->id);

        return $user;
    }

    protected function clearPermissionCache(): void
    {
        app(PermissionCacheService::class)->flushAll();
    }

    protected function clearUserPermissionCache(int $userId): void
    {
        app(PermissionCacheService::class)->forgetUser($userId);
    }

    /**
     * Re-sync role → permission assignments from the RoleCatalog config.
     *
     * The seeder runs early when some feature providers are fail-closed,
     * so their Permission rows don't exist yet. After BootsFeatures
     * creates them, this re-applies the role config so every declared
     * permission is actually linked.
     */
    protected function resyncRolePermissions(): void
    {
        foreach (RoleCatalog::roles() as $slug => $definition) {
            $role = Role::where('slug', $slug)->first();

            if (! $role || $slug === 'admin') {
                continue;
            }

            $entries = is_array($definition['permissions'] ?? []) ? $definition['permissions'] : [$definition['permissions'] ?? '*'];
            $excludedSlugs = [];

            foreach ($entries as $entry) {
                if (is_string($entry) && str_starts_with($entry, '!')) {
                    $excludedSlugs[] = substr($entry, 1);
                }
            }

            if (in_array('*', $entries, true)) {
                $ids = Permission::pluck('id')->all();
            } else {
                $slugs = array_values(array_filter($entries, fn ($e) => $e !== '*' && ! str_starts_with((string) $e, '!')));
                $ids = Permission::whereIn('slug', $slugs)->pluck('id')->all();
            }

            if ($excludedSlugs !== []) {
                $excludedIds = Permission::whereIn('slug', $excludedSlugs)->pluck('id')->all();
                $ids = array_values(array_diff($ids, $excludedIds));
            }

            $role->permissions()->sync(array_values(array_unique($ids)));
        }
    }
}
