<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
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

    public function test_admin_cannot_impersonate_themselves(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->post(route('dashboard.admin.impersonate.start', ['user' => $admin->id]));

        $response->assertRedirect();
        $response->assertSessionHas('flash.error', 'You cannot impersonate yourself.');
    }

    public function test_impersonating_non_existent_user_returns_404(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->post(route('dashboard.admin.impersonate.start', ['user' => 999999]));

        $response->assertStatus(404);
    }

    public function test_non_admin_cannot_impersonate_admin(): void
    {
        $admin = $this->admin();
        $user = $this->regularUser();

        $response = $this->actingAs($user)
            ->post(route('dashboard.admin.impersonate.start', ['user' => $admin->id]));

        $response->assertStatus(403);
    }

    public function test_stop_without_impersonating_returns_error(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)
            ->post(route('dashboard.admin.impersonate.stop'));

        $response->assertRedirect();
        $response->assertSessionHas('flash.error', 'Not currently impersonating.');
    }
}
