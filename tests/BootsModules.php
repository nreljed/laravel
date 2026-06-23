<?php

namespace Tests;

use App\Models\Permission;
use App\Models\Role;
use App\Modules\ModuleLoader;
use App\Modules\ModuleRegistry;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Schema;

trait BootsModules
{
    protected function bootModules(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $registry = app(ModuleRegistry::class);
        $loader = app(ModuleLoader::class);

        if ($registry->all()->isEmpty()) {
            $loader->load();
        }

        app(ModuleSeeder::class)->run();
        app(RolesAndPermissionsSeeder::class)->run();

        $registry->bootEnabled();
        $registry->registerRoutes();

        app('router')->getRoutes()->refreshNameLookups();

        // Create module permissions in DB (not done by seeders)
        $existing = Permission::pluck('slug')->toArray();
        foreach ($registry->all() as $module) {
            foreach ($module->permissions() as $perm) {
                if (! in_array($perm['slug'], $existing)) {
                    Permission::create([
                        'name' => $perm['name'],
                        'slug' => $perm['slug'],
                        'description' => $perm['description'] ?? '',
                        'guard_name' => 'web',
                    ]);
                    $existing[] = $perm['slug'];
                }
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

        // Sync all permissions to admin role
        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole) {
            $adminRole->permissions()->sync(Permission::all()->pluck('id'));
        }
    }
}
