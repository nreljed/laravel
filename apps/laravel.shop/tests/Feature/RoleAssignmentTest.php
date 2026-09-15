<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Models\Role;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_registration_assigns_customer_role(): void
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
        $this->assertTrue($user->hasRole('customer'));
        $this->assertFalse($user->isAdmin());
    }

    public function test_shop_owner_has_expected_permissions(): void
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'shop_owner')->first();
        $user->roles()->attach($role);

        $user->load('roles');

        $this->assertTrue($user->hasPermission('view-dashboard'));
        $this->assertTrue($user->hasPermission('view-profile'));
        $this->assertTrue($user->hasPermission('edit-profile'));
        $this->assertFalse($user->hasPermission('manage-users'));
        $this->assertFalse($user->hasPermission('manage-settings'));
        $this->assertFalse($user->hasPermission('manage-roles'));
        $this->assertFalse($user->hasPermission('manage_options'));
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
        $this->assertTrue($user->hasPermission('manage_options'));
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
        $this->assertTrue($user->hasRole('customer'));
    }
}
