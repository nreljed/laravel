<?php

namespace Nreljed\DashboardCore\Database\Seeders;

use Illuminate\Database\Seeder;
use Nreljed\DashboardCore\Models\Permission;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Services\FeatureRegistry;
use Nreljed\DashboardCore\Services\PermissionCacheService;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->syncPermissions();
        $this->syncRoles();

        app(PermissionCacheService::class)->flushAll();
    }

    protected function syncPermissions(): void
    {
        $catalog = array_merge(
            config('roles.permissions', []),
            app(FeatureRegistry::class)->permissions(),
        );

        foreach ($catalog as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => $permission['description'] ?? null,
                ],
            );
        }
    }

    protected function syncRoles(): void
    {
        foreach (config('roles.roles', []) as $slug => $definition) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'] ?? null,
                    'sort_order' => $definition['sort_order'] ?? 99,
                ],
            );

            $permissionIds = $this->resolvePermissionIds($definition['permissions'] ?? []);
            $role->permissions()->sync($permissionIds);
        }
    }

    protected function resolvePermissionIds(array|string $permissions): array
    {
        $entries = is_array($permissions) ? $permissions : [$permissions];

        $excludedSlugs = [];

        foreach ($entries as $entry) {
            if (is_string($entry) && str_starts_with($entry, '!')) {
                $excludedSlugs[] = substr($entry, 1);
            }
        }

        if (in_array('*', $entries, true)) {
            $ids = Permission::pluck('id')->all();
        } else {
            $slugs = array_values(array_filter($entries, fn ($entry) => $entry !== '*' && ! str_starts_with((string) $entry, '!')));
            $ids = Permission::whereIn('slug', $slugs)->pluck('id')->all();
        }

        if ($excludedSlugs !== []) {
            $excludedIds = Permission::whereIn('slug', $excludedSlugs)->pluck('id')->all();
            $ids = array_values(array_diff($ids, $excludedIds));
        }

        return array_values(array_unique($ids));
    }
}
