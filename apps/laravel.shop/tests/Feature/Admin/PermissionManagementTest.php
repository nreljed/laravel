<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Nreljed\Platform\Models\Permission;
use Nreljed\Platform\Models\Role;
use Tests\TestCase;

class PermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'admin')->first();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_admin_can_list_permissions(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.permissions.manage'));

        $response->assertStatus(200);
    }

    public function test_permissions_manage_page_lists_roles(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.permissions.manage'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('roles')
            ->has('groups'));
    }

    public function test_admin_can_grant_permission_to_role(): void
    {
        $moderator = Role::create(['name' => 'Moderator', 'slug' => 'moderator']);

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.permissions.toggle'), [
                'role' => 'moderator',
                'capability' => 'view-dashboard',
                'grant' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertTrue($moderator->permissions()->where('slug', 'view-dashboard')->exists());
    }

    public function test_system_roles_cannot_be_modified(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.permissions.toggle'), [
                'role' => 'admin',
                'capability' => 'view-dashboard',
                'grant' => true,
            ]);

        $response->assertStatus(400);
        $response->assertJsonStructure(['error']);
    }

    public function test_admin_can_revoke_permission_from_role(): void
    {
        $moderator = Role::create(['name' => 'Moderator', 'slug' => 'moderator']);
        $permission = Permission::where('slug', 'view-dashboard')->first();
        $moderator->permissions()->attach($permission);

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.permissions.toggle'), [
                'role' => 'moderator',
                'capability' => 'view-dashboard',
                'grant' => false,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertFalse($moderator->permissions()->where('slug', 'view-dashboard')->exists());
    }

    public function test_invalid_permission_toggle_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.permissions.toggle'), [
                'role' => 'customer',
                'capability' => 'does-not-exist',
                'grant' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('capability');
    }

    public function test_non_admin_cannot_manage_permissions(): void
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'customer')->first();
        $user->roles()->attach($role);

        $response = $this->actingAs($user)
            ->post(route('dashboard.admin.permissions.toggle'), [
                'role' => 'customer',
                'capability' => 'view-dashboard',
                'grant' => true,
            ]);

        $response->assertStatus(403);
    }
}
