<?php

namespace Tests\Feature\Deploy;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Models\DeployRelease;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class DeployVerticalTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_view_deploy_status_page(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.deploy.status'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/admin/deploy-status')
                ->has('deploy')
                ->where('deploy.status', 'idle')
                ->has('deploy.output'));
    }

    public function test_admin_can_poll_deploy_status_data(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($admin)->get(route('dashboard.admin.deploy.status.data'));

        $response->assertOk();
        $response->assertJsonStructure(['status', 'tag', 'output', 'core']);
        $response->assertJson(['status' => 'idle']);
    }

    public function test_admin_can_view_deploy_history_page(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.deploy.history'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/admin/deploy-history')
                ->has('releases'));
    }

    public function test_deploy_history_lists_recorded_releases(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        DeployRelease::forceCreate([
            'tag' => 'v9.9.9',
            'status' => DeployRelease::STATUS_ACTIVE,
            'deployed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.deploy.history'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/admin/deploy-history')
                ->has('releases', 1)
                ->where('releases.0.tag', 'v9.9.9')
                ->where('releases.0.status', DeployRelease::STATUS_ACTIVE));
    }

    public function test_rollback_route_is_not_registered(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)
            ->post('/dashboard/admin/deploy/history/v9.9.9/rollback')
            ->assertNotFound();
    }

    public function test_editor_is_forbidden_from_deploy_status(): void
    {
        $editor = $this->editor();
        $editor->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($editor)
            ->get(route('dashboard.admin.deploy.status'))
            ->assertForbidden();
    }

    public function test_editor_is_forbidden_from_deploy_history(): void
    {
        $editor = $this->editor();
        $editor->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($editor)
            ->get(route('dashboard.admin.deploy.history'))
            ->assertForbidden();
    }
}
