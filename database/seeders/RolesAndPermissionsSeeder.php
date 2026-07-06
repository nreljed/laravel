<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'Manage Users', 'slug' => 'manage-users', 'description' => 'Create, edit, delete users'],
            ['name' => 'Manage Settings', 'slug' => 'manage-settings', 'description' => 'Change application settings'],
            ['name' => 'Manage Roles', 'slug' => 'manage-roles', 'description' => 'Assign and manage roles'],
            ['name' => 'View System Health', 'slug' => 'view-system-health', 'description' => 'View system diagnostics'],
            ['name' => 'Manage Options', 'slug' => 'manage_options', 'description' => 'Module-level settings and system operations (admin only)'],
            ['name' => 'View Dashboard', 'slug' => 'view-dashboard', 'description' => 'Access the main dashboard'],
            ['name' => 'View Profile', 'slug' => 'view-profile', 'description' => 'View own profile'],
            ['name' => 'Edit Profile', 'slug' => 'edit-profile', 'description' => 'Edit own name, email, password'],
            ['name' => 'Delete Account', 'slug' => 'delete-account', 'description' => 'Delete own account'],
            ['name' => 'View Notifications', 'slug' => 'view-notifications', 'description' => 'View notifications'],
            ['name' => 'Manage Notifications', 'slug' => 'manage-notifications', 'description' => 'Mark read, delete notifications'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['slug' => $p['slug']], $p);
        }

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Full access to everything']
        );

        $crmUserRole = Role::firstOrCreate(
            ['slug' => 'crm_user'],
            ['name' => 'CRM User', 'description' => 'Standard user: modules + profile + notifications']
        );

        $adminRole->permissions()->sync(Permission::all()->pluck('id'));

        $crmUserRole->permissions()->sync(
            Permission::whereIn('slug', [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
                'manage-notifications',
                'shop.view',
            ])->pluck('id')
        );
    }
}
