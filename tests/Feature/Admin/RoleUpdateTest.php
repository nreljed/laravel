<?php

namespace Tests\Feature\Admin;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\DashboardCore\Models\Permission;
use Nreljed\DashboardCore\Models\Role;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class RoleUpdateTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_view_edit_form_for_custom_role(): void
    {
        $role = Role::factory()->create(['slug' => 'content-manager']);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.roles.edit', $role));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/admin/roles/form')
            ->where('role.id', $role->id)
            ->where('role.slug', 'content-manager')
            ->where('role.is_system', false)
            ->has('permissionGroups')
            ->has('roles')
        );
    }

    public function test_edit_form_marks_system_role_as_system(): void
    {
        $role = Role::where('slug', 'editor')->first();

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.roles.edit', $role));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/admin/roles/form')
            ->where('role.is_system', true)
        );
    }

    public function test_create_form_excludes_system_roles_from_base_options(): void
    {
        $role = Role::factory()->create(['slug' => 'content-manager']);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.roles.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/admin/roles/form')
            ->has('roles', 1)
            ->where('roles.0.slug', 'content-manager')
        );
    }

    public function test_admin_can_update_custom_role_with_permissions(): void
    {
        $admin = $this->admin();
        $role = Role::factory()->create(['slug' => 'content-manager']);
        $perm = Permission::where('slug', 'posts.view')->first();
        $other = Permission::where('slug', 'posts.create')->first();
        $role->permissions()->attach([$perm->id]);

        $response = $this->actingAs($admin)
            ->put(route('dashboard.admin.roles.update', $role), [
                'name' => 'Content Manager',
                'description' => 'Manages all content',
                'permission_ids' => [$perm->id, $other->id],
            ]);

        $response->assertRedirect(route('dashboard.admin.roles.index'));
        $response->assertSessionHas('flash.success');

        $role->refresh();
        $this->assertSame('Content Manager', $role->name);
        $this->assertSame('Manages all content', $role->description);
        $this->assertTrue($role->hasPermission('posts.create'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role_updated',
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role_permission_changed',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_update_role_name_without_changing_permissions(): void
    {
        $admin = $this->admin();
        $role = Role::factory()->create(['slug' => 'content-manager']);
        $perm = Permission::where('slug', 'posts.view')->first();
        $role->permissions()->attach([$perm->id]);

        $response = $this->actingAs($admin)
            ->put(route('dashboard.admin.roles.update', $role), [
                'name' => 'Renamed Role',
                'description' => $role->description,
                'permission_ids' => [$perm->id],
            ]);

        $response->assertRedirect();

        $role->refresh();
        $this->assertSame('Renamed Role', $role->name);
        $this->assertTrue($role->hasPermission('posts.view'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'role_updated', 'user_id' => $admin->id]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'role_permission_changed',
            'user_id' => $admin->id,
        ]);
    }

    public function test_system_role_cannot_be_updated(): void
    {
        $editor = Role::where('slug', 'editor')->first();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.admin.roles.update', $editor), [
                'name' => 'Hacked Editor',
                'description' => 'Should not apply',
            ]);

        $response->assertRedirect(route('dashboard.admin.roles.index'));
        $response->assertSessionHas('flash.error');

        $editor->refresh();
        $this->assertNotSame('Hacked Editor', $editor->name);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'role_updated']);
    }

    public function test_non_manager_cannot_update_role(): void
    {
        $role = Role::factory()->create(['slug' => 'content-manager']);

        $response = $this->actingAs($this->regularUser())
            ->put(route('dashboard.admin.roles.update', $role), [
                'name' => 'Hacked',
            ]);

        $response->assertStatus(403);

        $role->refresh();
        $this->assertNotSame('Hacked', $role->name);
    }
}
