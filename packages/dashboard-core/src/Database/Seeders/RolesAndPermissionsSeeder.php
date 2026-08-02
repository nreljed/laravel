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
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
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

    protected function resolvePermissionIds(array|string $slugs): array
    {
        if ($slugs === '*') {
            return Permission::pluck('id')->all();
        }

        return Permission::whereIn('slug', $slugs)->pluck('id')->all();
    }
}
