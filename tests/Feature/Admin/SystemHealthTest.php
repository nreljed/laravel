<?php

namespace Tests\Feature\Admin;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
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

        Cache::flush();
        File::delete(base_path('.deployed-version'));
        File::delete([storage_path('app/core.json'), storage_path('app/core.sha')]);
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

        $jsonFile = storage_path('app/core.json');
        File::put($jsonFile, json_encode([
            'name' => 'dashboard-core',
            'version' => '1.2.3',
            'sha' => '50e9c0fcf28d365295c46af725da4822456c3574',
            'repo' => 'nreljed/dashboard-core',
        ]));

        try {
            $response = $this->actingAs($this->admin())
                ->post(route('dashboard.admin.system-health.refresh'));

            $response->assertOk();
            $response->assertJsonPath('core_package.name', 'dashboard-core');
            $response->assertJsonPath('core_package.version', '1.2.3');
            $response->assertJsonPath('core_package.sha', '50e9c0fcf28d365295c46af725da4822456c3574');
            $response->assertJsonPath('core_package.short', '50e9c0f');
            $response->assertJsonPath('core_package.repo', 'nreljed/dashboard-core');
            $response->assertJsonPath('core_package.url', 'https://github.com/nreljed/dashboard-core/commit/50e9c0fcf28d365295c46af725da4822456c3574');
            $response->assertJsonPath('core_package.is_dev', false);
        } finally {
            File::delete($jsonFile);
        }
    }

    public function test_refresh_core_package_is_null_when_unrecorded(): void
    {
        Http::fake();

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.system-health.refresh'));

        $response->assertOk();
        $response->assertJsonPath('core_package.sha', null);
        $response->assertJsonPath('core_package.short', null);
        $response->assertJsonPath('core_package.url', null);
        $response->assertJsonPath('core_package.is_dev', false);
    }

    public function test_refresh_reports_core_latest_version_when_token_configured(): void
    {
        Env::getRepository()->set('GITHUB_TOKEN', 'test-token');

        Http::fake([
            'api.github.com/repos/nreljed/laravel/releases/latest' => Http::response([
                'tag_name' => 'v0.8.0',
                'published_at' => '2026-08-01T00:00:00Z',
            ], 200),
            'api.github.com/repos/nreljed/dashboard-core/releases/latest' => Http::response([
                'tag_name' => 'v0.1.2',
                'published_at' => '2026-08-02T00:00:00Z',
            ], 200),
        ]);

        try {
            $response = $this->actingAs($this->admin())
                ->post(route('dashboard.admin.system-health.refresh'));

            $response->assertOk();
            $response->assertJsonPath('latest_version.found', true);
            $response->assertJsonPath('latest_version.version', 'v0.8.0');
            $response->assertJsonPath('core_latest_version.found', true);
            $response->assertJsonPath('core_latest_version.version', 'v0.1.2');
            $response->assertJsonPath('core_latest_version.repo', 'nreljed/dashboard-core');
        } finally {
            Env::getRepository()->clear('GITHUB_TOKEN');
        }
    }

    public function test_deployed_version_uses_marker_when_present(): void
    {
        File::put(base_path('.deployed-version'), 'v9.9.9');

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.system-health'));

        $response->assertInertia(fn ($page) => $page
            ->where('deployed_version.exists', true)
            ->where('deployed_version.version', 'v9.9.9')
            ->where('deployed_version.origin', 'deployed')
        );
    }

    public function test_deployed_version_falls_back_to_git_describe_locally(): void
    {
        // Symfony renders the git commandline differently on Windows (full
        // path to git.EXE) and Linux (bare `git`), so match on distinctive
        // substrings instead of a `git*` prefix.
        Process::fake([
            '*--abbrev=0*' => Process::result(output: 'v0.8.1'),
            '*describe*' => Process::result(output: 'v0.8.1-5-g612ad63'),
            '*porcelain*' => Process::result(output: ''),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.system-health'));

        $response->assertInertia(fn ($page) => $page
            ->where('deployed_version.exists', true)
            ->where('deployed_version.version', 'v0.8.1')
            ->where('deployed_version.origin', 'git')
            ->where('deployed_version.describe', 'v0.8.1-5-g612ad63')
            ->where('deployed_version.is_dirty', false)
        );

        $ran = fn ($process) => is_string($process->command) ? $process->command : implode(' ', $process->command);
        Process::assertRanTimes(fn ($process) => str_contains($ran($process), 'describe'), 2);
        Process::assertRanTimes(fn ($process) => str_contains($ran($process), 'porcelain'), 1);
    }
}
