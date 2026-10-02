<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Models\Role;
use Tests\TestCase;

class ImpersonationTest extends TestCase
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

    protected function regularUser(): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'customer')->first();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_admin_can_impersonate_user(): void
    {
        $admin = $this->admin();
        $target = $this->regularUser();

        $response = $this->actingAs($admin)
            ->post(route('dashboard.admin.impersonate.start', ['user' => $target->id]));

        $response->assertRedirect('/dashboard');
        $this->assertEquals($target->id, auth()->id());
        $this->assertTrue(session()->has('impersonator_id'));
        $this->assertEquals($admin->id, session()->get('impersonator_id'));
    }

    public function test_impersonation_creates_audit_log(): void
    {
        $admin = $this->admin();
        $target = $this->regularUser();

        $this->actingAs($admin)
            ->post(route('dashboard.admin.impersonate.start', ['user' => $target->id]));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'impersonation_start',
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_stop_impersonation(): void
    {
        $admin = $this->admin();
        $target = $this->regularUser();

        $this->actingAs($admin)
            ->post(route('dashboard.admin.impersonate.start', ['user' => $target->id]));

        $response = $this->actingAs($target)
            ->post(route('dashboard.admin.impersonate.stop'));

        $response->assertRedirect(route('dashboard.admin.users.index'));
        $this->assertEquals($admin->id, auth()->id());
        $this->assertFalse(session()->has('impersonating'));
    }

    public function test_non_admin_cannot_impersonate(): void
    {
        $user1 = $this->regularUser();
        $user2 = $this->regularUser();

        $response = $this->actingAs($user1)
            ->post(route('dashboard.admin.impersonate.start', ['user' => $user2->id]));

        $response->assertStatus(403);
    }
}
