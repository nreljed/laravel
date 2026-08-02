<?php

namespace Tests\Feature\Admin;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Models\Setting;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Notifications\ResetPassword;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Setting::set('require_email_verification', '1');
    }

    // ─── Index ────────────────────────────────────────────

    public function test_admin_can_view_users_list(): void
    {
        User::factory()->count(3)->create();

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.users.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/admin/users/index')
            ->has('users', 4)
            ->has('roles')
        );
    }

    public function test_non_admin_cannot_view_users_list(): void
    {
        $response = $this->actingAs($this->regularUser())
            ->get(route('dashboard.admin.users.index'));

        $response->assertStatus(403);
    }

    // ─── Edit Form ───────────────────────────────────────

    public function test_admin_can_view_edit_form(): void
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.users.edit', $target));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/admin/users/form')
            ->has('user')
            ->where('user.id', $target->id)
            ->has('roles')
        );
    }

    public function test_non_admin_cannot_view_edit_form(): void
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->regularUser())
            ->get(route('dashboard.admin.users.edit', $target));

        $response->assertStatus(403);
    }

    // ─── Update ───────────────────────────────────────────

    public function test_admin_can_update_user_basic_info(): void
    {
        $target = User::factory()->create([
            'username' => 'original',
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.admin.users.update', $target), [
                'username' => 'updated',
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ]);

        $response->assertRedirect(route('dashboard.admin.users.index'));
        $response->assertSessionHas('flash.success');

        $target->refresh();
        $this->assertSame('updated', $target->username);
        $this->assertSame('Updated Name', $target->name);
        $this->assertSame('updated@example.com', $target->email);
    }

    public function test_admin_can_update_user_role(): void
    {
        $target = User::factory()->create();
        $role = Role::where('slug', 'editor')->first();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.admin.users.update', $target), [
                'username' => $target->username,
                'name' => $target->name,
                'email' => $target->email,
                'role_id' => $role->id,
            ]);

        $response->assertRedirect();

        $target->refresh();
        $this->assertTrue($target->hasRole('editor'));
    }

    public function test_admin_can_update_user_password(): void
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.admin.users.update', $target), [
                'username' => $target->username,
                'name' => $target->name,
                'email' => $target->email,
                'password' => 'NewSecurePass123!',
                'password_confirmation' => 'NewSecurePass123!',
            ]);

        $response->assertRedirect();

        $this->assertTrue(
            password_verify('NewSecurePass123!', $target->fresh()->password)
        );
    }

    public function test_admin_can_update_user_without_changing_role(): void
    {
        $editor = Role::where('slug', 'editor')->first();
        $target = User::factory()->create();
        $target->roles()->attach($editor);

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.admin.users.update', $target), [
                'username' => $target->username,
                'name' => $target->name,
                'email' => $target->email,
            ]);

        $response->assertRedirect();

        $target->refresh();
        $this->assertTrue($target->hasRole('editor'));
    }

    public function test_validation_fails_on_update_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);
        $target = User::factory()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.admin.users.update', $target), [
                'username' => $target->username,
                'name' => $target->name,
                'email' => 'existing@example.com',
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_validation_fails_on_update_with_invalid_data(): void
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.admin.users.update', $target), [
                'username' => 'ab',
                'name' => '',
                'email' => 'not-an-email',
            ]);

        $response->assertSessionHasErrors(['username', 'name', 'email']);
    }

    public function test_non_admin_cannot_update_user(): void
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->regularUser())
            ->put(route('dashboard.admin.users.update', $target), [
                'username' => 'hacked',
                'name' => 'Hacked Name',
                'email' => 'hacked@example.com',
            ]);

        $response->assertStatus(403);

        $target->refresh();
        $this->assertNotSame('hacked', $target->username);
    }

    // ─── Delete ───────────────────────────────────────────

    public function test_admin_can_delete_user(): void
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('dashboard.admin.users.destroy', $target));

        $response->assertRedirect(route('dashboard.admin.users.index'));
        $response->assertSessionHas('flash.success');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->delete(route('dashboard.admin.users.destroy', $admin));

        $response->assertRedirect(route('dashboard.admin.users.index'));
        $response->assertSessionHas('flash.error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_admin_cannot_delete_user(): void
    {
        $target = User::factory()->create();

        $response = $this->actingAs($this->regularUser())
            ->delete(route('dashboard.admin.users.destroy', $target));

        $response->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    // ─── Verify Email ─────────────────────────────────────

    public function test_admin_can_verify_user_email(): void
    {
        $target = User::factory()->unverified()->create();

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.users.verify-email', $target));

        $response->assertRedirect(route('dashboard.admin.users.index'));
        $response->assertSessionHas('flash.success');

        $this->assertTrue($target->fresh()->hasVerifiedEmail());
    }

    public function test_admin_cannot_verify_already_verified_email(): void
    {
        $target = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.users.verify-email', $target));

        $response->assertRedirect(route('dashboard.admin.users.index'));
        $response->assertSessionHas('flash.info');
    }

    public function test_non_admin_cannot_verify_user_email(): void
    {
        $target = User::factory()->unverified()->create();

        $response = $this->actingAs($this->regularUser())
            ->post(route('dashboard.admin.users.verify-email', $target));

        $response->assertStatus(403);

        $this->assertFalse($target->fresh()->hasVerifiedEmail());
    }

    // ─── Send Password Reset ──────────────────────────────

    public function test_admin_can_send_password_reset_link(): void
    {
        Notification::fake();

        $target = User::factory()->create();

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.users.send-reset-link', $target));

        $response->assertRedirect(route('dashboard.admin.users.index'));
        $response->assertSessionHas('flash.success');

        Notification::assertSentTo($target, ResetPassword::class);
    }

    public function test_non_admin_cannot_send_password_reset_link(): void
    {
        Notification::fake();

        $target = User::factory()->create();

        $response = $this->actingAs($this->regularUser())
            ->post(route('dashboard.admin.users.send-reset-link', $target));

        $response->assertStatus(403);

        Notification::assertNothingSent();
    }
}
