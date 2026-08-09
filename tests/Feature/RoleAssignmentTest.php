<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\DashboardCore\Models\Permission;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Models\User;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ─── Seeder Integrity ─────────────────────────────────

    public function test_admin_role_has_manage_options_permission(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $this->assertNotNull($adminRole);

        $adminRole->load('permissions');
        $slugs = $adminRole->permissions->pluck('slug')->toArray();

        $this->assertContains('manage-options', $slugs);
    }

    public function test_user_role_does_not_have_admin_permissions(): void
    {
        $crmRole = Role::where('slug', 'user')->first();
        $this->assertNotNull($crmRole);

        $crmRole->load('permissions');
        $slugs = $crmRole->permissions->pluck('slug')->toArray();

        $this->assertNotContains('manage_users', $slugs);
        $this->assertNotContains('manage-options', $slugs);
        $this->assertNotContains('manage-settings', $slugs);
        $this->assertNotContains('manage-roles', $slugs);
        $this->assertNotContains('view-system-health', $slugs);
    }

    public function test_manage_options_permission_exists_in_database(): void
    {
        $perm = Permission::where('slug', 'manage-options')->first();
        $this->assertNotNull($perm);
        $this->assertEquals('Manage Options', $perm->name);
    }

    public function test_manage_options_only_assigned_to_admin(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();
        $crmRole = Role::where('slug', 'user')->first();

        $this->assertTrue(
            $adminRole->permissions()->where('slug', 'manage-options')->exists()
        );

        $this->assertFalse(
            $crmRole->permissions()->where('slug', 'manage-options')->exists()
        );
    }

    // ─── Registration Role Assignment ─────────────────────

    public function test_registration_assigns_user_role(): void
    {
        $response = $this->post('/register', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard.index', absolute: false));

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('editor'));
        $this->assertFalse($user->isAdmin());
    }

    public function test_user_has_expected_permissions(): void
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'user')->first();
        $user->roles()->attach($role);

        $user->load('roles');

        $this->assertTrue($user->hasPermission('view-dashboard'));
        $this->assertTrue($user->hasPermission('view-profile'));
        $this->assertTrue($user->hasPermission('edit-profile'));
        $this->assertFalse($user->hasPermission('manage-users'));
        $this->assertFalse($user->hasPermission('manage-settings'));
        $this->assertFalse($user->hasPermission('manage-roles'));
        $this->assertFalse($user->hasPermission('manage-options'));
        $this->assertFalse($user->hasPermission('view-system-health'));
    }

    public function test_admin_user_has_all_permissions(): void
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'admin')->first();
        $user->roles()->attach($role);

        $user->load('roles');

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->hasPermission('manage-users'));
        $this->assertTrue($user->hasPermission('manage-settings'));
        $this->assertTrue($user->hasPermission('manage-roles'));
        $this->assertTrue($user->hasPermission('manage-options'));
        $this->assertTrue($user->hasPermission('view-system-health'));
        $this->assertTrue($user->hasPermission('view-dashboard'));
    }

    public function test_admin_user_cannot_be_registered_through_normal_registration(): void
    {
        $this->post('/register', [
            'username' => 'hacker',
            'email' => 'hacker@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'hacker@example.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->hasRole('editor'));
    }
}
