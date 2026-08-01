<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Notifications\WelcomeNewUser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class UserCreationTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_view_create_user_form(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.users.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/admin/users/form')
            ->has('roles')
        );
    }

    public function test_admin_can_create_user_without_role(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.users.store'), [
                'username' => 'newuser',
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'SecurePass123!',
            ]);

        $response->assertRedirect(route('dashboard.admin.users.index'));
        $response->assertSessionHas('flash.success');

        $this->assertDatabaseHas('users', [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
        ]);

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertFalse($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
    }

    public function test_admin_can_create_user_with_role(): void
    {
        $role = Role::where('slug', 'editor')->first();

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.users.store'), [
                'username' => 'editoruser',
                'name' => 'Editor User',
                'email' => 'editor@example.com',
                'password' => 'SecurePass123!',
                'role_id' => $role->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success');

        $user = User::where('email', 'editor@example.com')->first();
        $this->assertTrue($user->hasRole('editor'));
    }

    public function test_admin_can_create_user_with_role_and_send_email(): void
    {
        Notification::fake();

        $role = Role::where('slug', 'editor')->first();

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.users.store'), [
                'username' => 'notifieduser',
                'name' => 'Notified User',
                'email' => 'notified@example.com',
                'password' => 'SecurePass123!',
                'role_id' => $role->id,
                'send_email' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', fn ($msg) => str_contains($msg, 'welcome email'));

        $user = User::where('email', 'notified@example.com')->first();
        Notification::assertSentTo($user, WelcomeNewUser::class);
    }

    public function test_admin_can_create_user_without_send_email(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.users.store'), [
                'username' => 'nonotified',
                'name' => 'No Email',
                'email' => 'nonotified@example.com',
                'password' => 'SecurePass123!',
                'send_email' => false,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', fn ($msg) => ! str_contains($msg, 'welcome email'));

        $user = User::where('email', 'nonotified@example.com')->first();
        Notification::assertNothingSent();
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'user')->first();
        $user->roles()->attach($role);

        $response = $this->actingAs($user)
            ->post(route('dashboard.admin.users.store'), [
                'username' => 'regularuser',
                'name' => 'Regular User',
                'email' => 'regular@example.com',
                'password' => 'SecurePass123!',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'regular@example.com']);
    }

    public function test_validation_fails_without_required_fields(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.users.store'), [
                'username' => '',
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
            ]);

        $response->assertSessionHasErrors(['username', 'name', 'email', 'password']);
    }

    public function test_validation_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.users.store'), [
                'username' => 'uniqueuser',
                'name' => 'Unique User',
                'email' => 'existing@example.com',
                'password' => 'SecurePass123!',
            ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_audit_log_created_when_role_assigned(): void
    {
        $admin = $this->admin();
        $role = Role::where('slug', 'editor')->first();

        $this->actingAs($admin)
            ->post(route('dashboard.admin.users.store'), [
                'username' => 'audituser',
                'name' => 'Audit User',
                'email' => 'audit@example.com',
                'password' => 'SecurePass123!',
                'role_id' => $role->id,
            ]);

        $user = User::where('email', 'audit@example.com')->first();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role_assigned',
            'user_id' => $admin->id,
        ]);
    }

    public function test_audit_log_when_default_role_assigned(): void
    {
        $admin = $this->admin();
        $editorRole = Role::where('slug', 'editor')->first();

        $this->actingAs($admin)
            ->post(route('dashboard.admin.users.store'), [
                'username' => 'defaultroleuser',
                'name' => 'Default Role User',
                'email' => 'defaultrole@example.com',
                'password' => 'SecurePass123!',
            ]);

        $user = User::where('email', 'defaultrole@example.com')->first();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role_assigned',
            'user_id' => $admin->id,
        ]);

        $this->assertTrue($user->hasRole('editor'));
    }
}
