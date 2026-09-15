<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Models\Role;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
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

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->actingAs($this->admin())->get('/dashboard/admin/users');

        $response->assertStatus(200);
    }

    public function test_admin_can_create_user_with_role(): void
    {
        $customerRole = Role::where('slug', 'customer')->first();

        $response = $this->actingAs($this->admin())->post('/dashboard/admin/users', [
            'username' => 'janedoe',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'securepass123',
            'role_id' => $customerRole->id,
        ]);

        $response->assertRedirect();

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('customer'));
    }

    public function test_admin_can_edit_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin())->get("/dashboard/admin/users/{$user->id}/edit");

        $response->assertStatus(200);
    }

    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin())->put("/dashboard/admin/users/{$user->id}", [
            'username' => $user->username,
            'name' => 'Updated Name',
            'email' => $user->email,
        ]);

        $response->assertRedirect();
        $this->assertEquals('Updated Name', $user->fresh()->name);
    }

    public function test_admin_can_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin())->delete("/dashboard/admin/users/{$user->id}");

        $response->assertRedirect();
        $this->assertNull($user->fresh());
    }

    public function test_admin_can_update_user_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())->put("/dashboard/admin/users/{$user->id}", [
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword123',
        ]);

        $this->assertNotNull($user->fresh());
    }
}
