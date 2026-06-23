<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_has_manage_options_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        $adminRole->load('permissions');
        $slugs = $adminRole->permissions->pluck('slug')->toArray();

        $this->assertContains('manage_options', $slugs);
    }

    public function test_crm_user_role_does_not_have_admin_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $crmRole = Role::where('slug', 'crm_user')->first();
        $this->assertNotNull($crmRole);

        $crmRole->load('permissions');
        $slugs = $crmRole->permissions->pluck('slug')->toArray();

        $this->assertNotContains('manage_users', $slugs);
        $this->assertNotContains('manage_options', $slugs);
        $this->assertNotContains('manage-settings', $slugs);
        $this->assertNotContains('manage-roles', $slugs);
        $this->assertNotContains('view-system-health', $slugs);
    }

    public function test_manage_options_permission_exists_in_database(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $perm = Permission::where('slug', 'manage_options')->first();
        $this->assertNotNull($perm);
        $this->assertEquals('Manage Options', $perm->name);
    }

    public function test_manage_options_only_assigned_to_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $adminRole = Role::where('slug', 'admin')->first();
        $crmRole = Role::where('slug', 'crm_user')->first();

        $this->assertTrue(
            $adminRole->permissions()->where('slug', 'manage_options')->exists()
        );

        $this->assertFalse(
            $crmRole->permissions()->where('slug', 'manage_options')->exists()
        );
    }
}
