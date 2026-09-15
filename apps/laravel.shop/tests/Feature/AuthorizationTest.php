<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Models\Setting;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Setting::set('require_email_verification', '1');
    }

    protected function createUserWithRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', $roleSlug)->first();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $user = $this->createUserWithRole('customer');

        $response = $this->actingAs($user)->get('/dashboard/admin/settings');

        $response->assertStatus(403);
    }

    public function test_customer_cannot_access_admin_users_page(): void
    {
        $user = $this->createUserWithRole('customer');

        $response = $this->actingAs($user)->get('/dashboard/admin/users');

        $response->assertStatus(403);
    }

    public function test_customer_cannot_access_admin_roles_page(): void
    {
        $user = $this->createUserWithRole('customer');

        $response = $this->actingAs($user)->get('/dashboard/admin/roles');

        $response->assertStatus(403);
    }

    public function test_customer_cannot_access_system_health(): void
    {
        $user = $this->createUserWithRole('customer');

        $response = $this->actingAs($user)->get('/dashboard/admin/system-health');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = $this->createUserWithRole('admin');

        $response = $this->actingAs($user)->get('/dashboard/admin/settings/general');

        $response->assertStatus(200);
    }

    public function test_admin_can_access_users_page(): void
    {
        $user = $this->createUserWithRole('admin');

        $response = $this->actingAs($user)->get('/dashboard/admin/users');

        $response->assertStatus(200);
    }

    public function test_admin_can_access_roles_page(): void
    {
        $user = $this->createUserWithRole('admin');

        $response = $this->actingAs($user)->get('/dashboard/admin/roles');

        $response->assertStatus(200);
    }

    public function test_guest_cannot_access_any_dashboard_page(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');

        $response = $this->get('/dashboard/admin/settings');
        $response->assertRedirect('/login');
    }

    public function test_unverified_user_can_access_dashboard(): void
    {
        $user = User::factory()->unverified()->create();
        $role = Role::where('slug', 'customer')->first();
        $user->roles()->attach($role);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_customer_cannot_create_users(): void
    {
        $user = $this->createUserWithRole('customer');

        $response = $this->actingAs($user)->post('/dashboard/admin/users', [
            'username' => 'newuser',
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_create_user(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->post('/dashboard/admin/users', [
            'username' => 'newuser',
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }
}
