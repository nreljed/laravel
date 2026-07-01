<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->get(route('dashboard.admin.permissions.index'));

        $response->assertStatus(200);
    }

    public function test_admin_can_view_create_permission_form(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.permissions.create'));

        $response->assertStatus(200);
    }

    public function test_admin_can_create_permission(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.permissions.store'), [
                'name' => 'View Reports',
                'slug' => 'view-reports',
                'description' => 'View analytics reports',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('permissions', ['slug' => 'view-reports']);
    }

    public function test_admin_can_edit_permission(): void
    {
        $permission = Permission::factory()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.permissions.edit', $permission));

        $response->assertStatus(200);
    }

    public function test_admin_can_update_permission(): void
    {
        $permission = Permission::factory()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.admin.permissions.update', $permission), [
                'name' => 'Updated Permission',
                'slug' => $permission->slug,
            ]);

        $response->assertRedirect();
        $this->assertEquals('Updated Permission', $permission->fresh()->name);
    }

    public function test_admin_can_delete_permission(): void
    {
        $permission = Permission::factory()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('dashboard.admin.permissions.destroy', $permission));

        $response->assertRedirect();
        $this->assertNull($permission->fresh());
    }

    public function test_non_admin_cannot_create_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'crm_user')->first();
        $user->roles()->attach($role);

        $response = $this->actingAs($user)
            ->post(route('dashboard.admin.permissions.store'), [
                'name' => 'View Reports',
                'slug' => 'view-reports',
            ]);

        $response->assertStatus(403);
    }
}
