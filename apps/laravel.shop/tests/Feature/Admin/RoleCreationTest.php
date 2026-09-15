<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Models\Permission;
use Nreljed\Platform\Models\Role;
use Tests\TestCase;

class RoleCreationTest extends TestCase
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

    public function test_admin_can_view_create_role_form(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.roles.create'));

        $response->assertStatus(200);
    }

    public function test_admin_can_create_role(): void
    {
        $perm = Permission::first();

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.roles.store'), [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Can edit content',
                'permission_ids' => [$perm->id],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('roles', ['slug' => 'editor']);
    }

    public function test_admin_can_delete_role(): void
    {
        $role = Role::factory()->create(['slug' => 'temporary_role']);

        $response = $this->actingAs($this->admin())
            ->delete(route('dashboard.admin.roles.destroy', $role));

        $response->assertRedirect();
        $this->assertNull($role->fresh());
    }

    public function test_admin_role_cannot_be_deleted(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();

        $response = $this->actingAs($this->admin())
            ->delete(route('dashboard.admin.roles.destroy', $adminRole));

        $response->assertRedirect();
        $response->assertSessionHas('flash.error');
        $this->assertNotNull($adminRole->fresh());
    }

    public function test_non_admin_cannot_create_role(): void
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'customer')->first();
        $user->roles()->attach($role);

        $response = $this->actingAs($user)
            ->post(route('dashboard.admin.roles.store'), [
                'name' => 'Editor',
                'slug' => 'editor',
            ]);

        $response->assertStatus(403);
    }

    public function test_create_role_command(): void
    {
        $this->artisan('roles:create', [
            'name' => 'Moderator',
            '--permissions' => 'view-dashboard,view-profile',
            '--description' => 'Moderator role',
        ])->assertSuccessful();

        $this->assertDatabaseHas('roles', ['slug' => 'moderator']);

        $role = Role::where('slug', 'moderator')->first();
        $this->assertCount(2, $role->permissions);
    }
}
