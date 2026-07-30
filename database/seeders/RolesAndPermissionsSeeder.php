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
            ['name' => 'Manage Options', 'slug' => 'manage_options', 'description' => 'Feature-level settings and system operations (admin only)'],
            ['name' => 'Manage Audit Logs', 'slug' => 'manage-audit-logs', 'description' => 'View system audit logs'],
            ['name' => 'View Dashboard', 'slug' => 'view-dashboard', 'description' => 'Access the main dashboard'],
            ['name' => 'View Profile', 'slug' => 'view-profile', 'description' => 'View own profile'],
            ['name' => 'Edit Profile', 'slug' => 'edit-profile', 'description' => 'Edit own name, email, password'],
            ['name' => 'Delete Account', 'slug' => 'delete-account', 'description' => 'Delete own account'],
            ['name' => 'View Notifications', 'slug' => 'view-notifications', 'description' => 'View notifications'],
            ['name' => 'Manage Notifications', 'slug' => 'manage-notifications', 'description' => 'Mark read, delete notifications'],
            ['name' => 'View Posts', 'slug' => 'posts.view', 'description' => 'View blog posts'],
            ['name' => 'Create Posts', 'slug' => 'posts.create', 'description' => 'Create new blog posts'],
            ['name' => 'Edit Posts', 'slug' => 'posts.edit', 'description' => 'Edit existing blog posts'],
            ['name' => 'Delete Posts', 'slug' => 'posts.delete', 'description' => 'Delete blog posts'],
            ['name' => 'Manage Categories', 'slug' => 'manage-categories', 'description' => 'Manage post categories'],
            ['name' => 'Manage Tags', 'slug' => 'manage-tags', 'description' => 'Manage post tags'],
            ['name' => 'Manage Email Settings', 'slug' => 'manage-email-settings', 'description' => 'Configure email settings'],
            ['name' => 'View Tests', 'slug' => 'test-posts.view', 'description' => 'View tests'],
            ['name' => 'Create Tests', 'slug' => 'test-posts.create', 'description' => 'Create new tests'],
            ['name' => 'Edit Tests', 'slug' => 'test-posts.edit', 'description' => 'Edit existing tests'],
            ['name' => 'Delete Tests', 'slug' => 'test-posts.delete', 'description' => 'Delete tests'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['slug' => $p['slug']], $p);
        }

        // ─── user: own profile + dashboard + notifications ───
        $userRole = Role::firstOrCreate(
            ['slug' => 'user'],
            ['name' => 'User', 'description' => 'Can manage own account and profile']
        );
        $userRole->permissions()->sync(
            Permission::whereIn('slug', [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
                'manage-notifications',
            ])->pluck('id')
        );

        // ─── author: user + content creation (no delete) ───
        $authorRole = Role::firstOrCreate(
            ['slug' => 'author'],
            ['name' => 'Author', 'description' => 'Can add content and manage own account']
        );
        $authorRole->permissions()->sync(
            Permission::whereIn('slug', [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
                'manage-notifications',
                'posts.view',
                'posts.create',
                'posts.edit',
            ])->pluck('id')
        );

        // ─── editor: app management (no admin pages) ───
        $editorRole = Role::firstOrCreate(
            ['slug' => 'editor'],
            ['name' => 'Editor', 'description' => 'Can manage the application without admin privileges']
        );
        $editorRole->permissions()->sync(
            Permission::whereIn('slug', [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
                'manage-notifications',
                'posts.view',
                'posts.create',
                'posts.edit',
                'posts.delete',
                'manage-categories',
                'manage-tags',
                'manage-email-settings',
                'test-posts.view',
                'test-posts.create',
                'test-posts.edit',
                'test-posts.delete',
            ])->pluck('id')
        );

        // ─── admin: everything ───
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Full access to everything including admin panel']
        );
        $adminRole->permissions()->sync(Permission::all()->pluck('id'));
    }
}
