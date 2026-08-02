<?php

namespace Tests\Feature\Permissions;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nreljed\DashboardCore\Models\Permission;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Services\FeatureRegistry;
use Tests\TestCase;

class ConfigRoleCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_syncs_shared_permission_catalog(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $expected = count(config('roles.permissions', [])) + count(app(FeatureRegistry::class)->permissions());

        $this->assertSame($expected, Permission::count());
        $this->assertDatabaseHas('permissions', ['slug' => 'manage-users', 'name' => 'Manage Users']);
        $this->assertDatabaseHas('permissions', ['slug' => 'manage-options']);
        $this->assertDatabaseHas('permissions', ['slug' => 'posts.view']);
        $this->assertDatabaseHas('permissions', ['slug' => 'test-posts.view']);
    }

    public function test_seeder_creates_roles_from_config_with_expected_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = Role::where('slug', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertSame(0, $admin->sort_order);
        $this->assertSame(Permission::count(), $admin->permissions()->count());

        $editor = Role::where('slug', 'editor')->first();
        $this->assertSame(1, $editor->sort_order);
        $this->assertTrue($editor->hasPermission('posts.delete'));
        $this->assertTrue($editor->hasPermission('manage-categories'));
        $this->assertTrue($editor->hasPermission('manage-tags'));
        $this->assertTrue($editor->hasPermission('test-posts.delete'));
        $this->assertFalse($editor->hasPermission('manage-users'));
        $this->assertFalse($editor->hasPermission('manage-email-settings'));

        $author = Role::where('slug', 'author')->first();
        $this->assertTrue($author->hasPermission('posts.edit'));
        $this->assertTrue($author->hasPermission('posts.delete'));
        $this->assertTrue($author->hasPermission('test-posts.delete'));
        $this->assertFalse($author->hasPermission('manage-categories'));
        $this->assertFalse($author->hasPermission('manage-tags'));

        $user = Role::where('slug', 'user')->first();
        $this->assertTrue($user->hasPermission('edit-profile'));
        $this->assertFalse($user->hasPermission('posts.view'));
    }

    public function test_editor_role_grants_every_non_admin_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $editor = Role::where('slug', 'editor')->first();
        $adminOnly = [
            'manage-users',
            'manage-settings',
            'manage-roles',
            'view-system-health',
            'manage-options',
            'manage-audit-logs',
            'manage-email-settings',
        ];

        foreach (Permission::pluck('slug') as $slug) {
            $this->assertSame(
                ! in_array($slug, $adminOnly, true),
                $editor->hasPermission($slug),
                "Editor permission mismatch for [{$slug}].",
            );
        }
    }

    public function test_author_role_grants_every_non_admin_permission_except_term_management(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $author = Role::where('slug', 'author')->first();
        $excluded = [
            'manage-users',
            'manage-settings',
            'manage-roles',
            'view-system-health',
            'manage-options',
            'manage-audit-logs',
            'manage-email-settings',
            'manage-categories',
            'manage-tags',
        ];

        foreach (Permission::pluck('slug') as $slug) {
            $this->assertSame(
                ! in_array($slug, $excluded, true),
                $author->hasPermission($slug),
                "Author permission mismatch for [{$slug}].",
            );
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $permissionCount = Permission::count();
        $roleCount = Role::count();
        $assignments = DB::table('permission_role')->count();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame($permissionCount, Permission::count());
        $this->assertSame($roleCount, Role::count());
        $this->assertSame($assignments, DB::table('permission_role')->count());
    }

    public function test_per_app_catalog_overrides_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        config(['roles.roles' => [
            'manager' => [
                'name' => 'Manager',
                'description' => 'App-specific role',
                'sort_order' => 1,
                'permissions' => ['view-dashboard', 'posts.view'],
            ],
        ]]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $manager = Role::where('slug', 'manager')->first();
        $this->assertNotNull($manager);
        $this->assertSame('Manager', $manager->name);
        $this->assertSame(
            ['posts.view', 'view-dashboard'],
            $manager->permissions()->pluck('slug')->sort()->values()->all(),
        );

        // Roles removed from the catalog are preserved, not deleted.
        $this->assertNotNull(Role::where('slug', 'editor')->first());
    }

    public function test_admin_role_grants_newly_registered_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        config(['roles.permissions' => array_merge(config('roles.permissions', []), [
            ['name' => 'Manage Reports', 'slug' => 'manage-reports', 'description' => 'View and export reports'],
        ])]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseHas('permissions', ['slug' => 'manage-reports']);

        $admin = Role::where('slug', 'admin')->first();
        $this->assertTrue($admin->hasPermission('manage-reports'));

        // Editor and author inherit newly added non-admin permissions automatically.
        $editor = Role::where('slug', 'editor')->first();
        $this->assertTrue($editor->hasPermission('manage-reports'));

        $author = Role::where('slug', 'author')->first();
        $this->assertTrue($author->hasPermission('manage-reports'));
    }
}
