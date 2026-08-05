<?php

namespace Tests\Feature\Admin;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Nreljed\DashboardCore\Services\SystemHealthService;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_view_system_health(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.system-health'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/admin/system-health')
            ->has('php')
            ->has('laravel')
            ->has('server')
            ->has('storage.dirs')
            ->has('storage.total_size')
            ->has('storage.disk')
            ->has('mail.driver')
            ->has('mail.mailer')
            ->has('recommendations')
            ->has('latest_version')
        );
    }

    public function test_non_admin_cannot_view_system_health(): void
    {
        $response = $this->actingAs($this->regularUser())
            ->get(route('dashboard.admin.system-health'));

        $response->assertStatus(403);
    }

    public function test_system_health_mail_payload_does_not_leak_credentials(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.system-health'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->missing('mail.mailers')
            ->missing('mail.mailer.password')
            ->missing('mail.mailer.scheme')
            ->missing('mail.mailer.url')
            ->missing('mail.mailer.secret')
        );
    }

    public function test_refresh_returns_json_system_health(): void
    {
        Http::fake();

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.system-health.refresh'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'php' => ['version'],
            'mail' => ['mailer'],
            'recommendations',
        ]);
    }

    public function test_service_detects_dev_host_as_local(): void
    {
        app()->instance('request', Request::create('http://laravel.test'));

        $env = (new SystemHealthService)->detectEnvironment();

        $this->assertSame('local', $env['type']);
        $this->assertTrue($env['is_local']);
        $this->assertFalse($env['is_production']);
    }

    public function test_refresh_includes_core_package_when_recorded(): void
    {
        Http::fake();

        $shaFile = storage_path('app/core.sha');
        File::put($shaFile, "50e9c0fcf28d365295c46af725da4822456c3574\n");

        try {
            $response = $this->actingAs($this->admin())
                ->post(route('dashboard.admin.system-health.refresh'));

            $response->assertOk();
            $response->assertJsonPath('core_package.name', 'dashboard-core');
            $response->assertJsonPath('core_package.sha', '50e9c0fcf28d365295c46af725da4822456c3574');
            $response->assertJsonPath('core_package.short', '50e9c0f');
            $response->assertJsonPath('core_package.url', 'https://github.com/nreljed/laravel/commit/50e9c0fcf28d365295c46af725da4822456c3574');
        } finally {
            File::delete($shaFile);
        }
    }

    public function test_refresh_core_package_is_null_when_unrecorded(): void
    {
        Http::fake();
        File::delete(storage_path('app/core.sha'));

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.system-health.refresh'));

        $response->assertOk();
        $response->assertJsonPath('core_package.sha', null);
        $response->assertJsonPath('core_package.short', null);
        $response->assertJsonPath('core_package.url', null);
    }
}
