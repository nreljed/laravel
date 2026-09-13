<?php

namespace Tests\Feature\Admin;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Nreljed\Platform\Services\CoreVersionService;
use Nreljed\Platform\Services\SystemHealthService;
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
        Config::set('deploy.version_path', base_path('.deployed-version-'.getmypid()));
        File::delete(config('deploy.version_path'));
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

    public function test_admin_can_read_log_file_tail(): void
    {
        $logPath = storage_path('logs/health-test.log');
        File::put($logPath, "[2026-08-08 10:00:00] production.ERROR: Test failure\n[2026-08-08 10:00:01] production.INFO: All good\n");

        try {
            $response = $this->actingAs($this->admin())
                ->get(route('dashboard.admin.system-health.logs', ['file' => 'health-test.log']));

            $response->assertOk();
            $response->assertJsonPath('name', 'health-test.log');
            $response->assertJsonPath('lines.0', '[2026-08-08 10:00:00] production.ERROR: Test failure');
            $response->assertJsonPath('lines.1', '[2026-08-08 10:00:01] production.INFO: All good');
            $response->assertJsonPath('truncated', false);
        } finally {
            File::delete($logPath);
        }
    }

    public function test_non_admin_cannot_read_log_file(): void
    {
        $response = $this->actingAs($this->regularUser())
            ->get(route('dashboard.admin.system-health.logs', ['file' => 'laravel.log']));

        $response->assertStatus(403);
    }

    public function test_log_read_rejects_path_traversal(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.system-health.logs', ['file' => '../laravel.log']));

        $this->assertContains($response->getStatusCode(), [400, 404]);
    }

    public function test_read_log_rejects_path_traversal_at_service_level(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SystemHealthService)->readLog('../laravel.log');
    }

    public function test_log_read_returns_error_for_missing_file(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.system-health.logs', ['file' => 'does-not-exist.log']));

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'Log file not found.');
    }

    public function test_admin_can_view_log_viewer_page(): void
    {
        $logPath = storage_path('logs/health-test.log');
        File::put($logPath, "[2026-08-08 10:00:00] production.ERROR: First\n[2026-08-08 10:00:01] production.INFO: Second\n");

        try {
            $response = $this->actingAs($this->admin())
                ->get(route('dashboard.admin.logs', ['file' => 'health-test.log']));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('dashboard/admin/logs')
                ->has('files')
                ->where('selected', 'health-test.log')
                ->where('log.name', 'health-test.log')
                ->where('log.total_lines', 2)
                ->where('log.total_pages', 1)
                ->has('log.lines', 2)
            );
        } finally {
            File::delete($logPath);
        }
    }

    public function test_non_admin_cannot_view_log_viewer_page(): void
    {
        $response = $this->actingAs($this->regularUser())
            ->get(route('dashboard.admin.logs'));

        $response->assertStatus(403);
    }

    public function test_log_viewer_defaults_to_first_file(): void
    {
        File::put(storage_path('logs/0-zzz.log'), "line z\n");
        File::put(storage_path('logs/0-aaa.log'), "line a\n");

        try {
            $response = $this->actingAs($this->admin())
                ->get(route('dashboard.admin.logs'));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('dashboard/admin/logs')
                ->where('selected', '0-aaa.log')
            );
        } finally {
            File::delete([storage_path('logs/0-zzz.log'), storage_path('logs/0-aaa.log')]);
        }
    }

    public function test_log_viewer_falls_back_to_first_file_for_invalid_file(): void
    {
        File::put(storage_path('logs/0-file.log'), "line one\n");

        try {
            $response = $this->actingAs($this->admin())
                ->get(route('dashboard.admin.logs', ['file' => 'nonexistent.log']));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->where('selected', '0-file.log')
            );
        } finally {
            File::delete(storage_path('logs/0-file.log'));
        }
    }

    public function test_read_log_page_paginates_from_most_recent(): void
    {
        $logPath = storage_path('logs/health-test.log');
        File::put($logPath, implode("\n", array_map(fn ($i) => "Line {$i}", range(1, 100))));

        try {
            $service = new SystemHealthService;

            $page1 = $service->readLogPage('health-test.log', 25, 1);
            $this->assertSame(1, $page1['page']);
            $this->assertSame(100, $page1['total_lines']);
            $this->assertSame(4, $page1['total_pages']);
            $this->assertSame(array_map(fn ($i) => "Line {$i}", range(76, 100)), $page1['lines']);

            $page4 = $service->readLogPage('health-test.log', 25, 4);
            $this->assertSame(4, $page4['page']);
            $this->assertSame(array_map(fn ($i) => "Line {$i}", range(1, 25)), $page4['lines']);

            $overflow = $service->readLogPage('health-test.log', 25, 99);
            $this->assertSame(4, $overflow['page']);
        } finally {
            File::delete($logPath);
        }
    }

    public function test_admin_can_download_log_file(): void
    {
        $logPath = storage_path('logs/health-test.log');
        File::put($logPath, "hello world\n");

        try {
            $response = $this->actingAs($this->admin())
                ->get(route('dashboard.admin.logs.download', ['file' => 'health-test.log']));

            $response->assertOk();
            $response->assertDownload('health-test.log');
        } finally {
            File::delete($logPath);
        }
    }

    public function test_non_admin_cannot_download_log_file(): void
    {
        $response = $this->actingAs($this->regularUser())
            ->get(route('dashboard.admin.logs.download', ['file' => 'laravel.log']));

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
            'name' => 'platform',
            'version' => '1.2.3',
            'sha' => '50e9c0fcf28d365295c46af725da4822456c3574',
            'repo' => 'nreljed/platform',
        ]));

        try {
            $response = $this->actingAs($this->admin())
                ->post(route('dashboard.admin.system-health.refresh'));

            $response->assertOk();
            $response->assertJsonPath('core_package.name', 'platform');
            $response->assertJsonPath('core_package.version', '1.2.3');
            $response->assertJsonPath('core_package.sha', '50e9c0fcf28d365295c46af725da4822456c3574');
            $response->assertJsonPath('core_package.short', '50e9c0f');
            $response->assertJsonPath('core_package.repo', 'nreljed/platform');
            $response->assertJsonPath('core_package.url', 'https://github.com/nreljed/platform/commit/50e9c0fcf28d365295c46af725da4822456c3574');
            $response->assertJsonPath('core_package.is_dev', false);
        } finally {
            File::delete($jsonFile);
        }
    }

    public function test_refresh_core_package_sha_is_null_when_unrecorded(): void
    {
        Http::fake();

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.system-health.refresh'));

        $response->assertOk();
        $response->assertJsonPath('core_package.sha', null);
        $response->assertJsonPath('core_package.short', null);
        $response->assertJsonPath('core_package.url', null);
        // Model B: no core.json/core.sha marker, but the install is a pinned
        // VCS build (^0.1 with a stamped version) — NOT a dev build.
        $response->assertJsonPath('core_package.is_dev', false);
        $response->assertJsonPath('core_latest_version.found', false);
        $response->assertJsonPath('core_latest_version.skipped', true);

        $payload = $response->json();
        $this->assertIsString($payload['core_latest_version']['checked_at'] ?? null);
        $this->assertSame(
            'No GITHUB_TOKEN configured; skipped core release check.',
            $payload['core_latest_version']['message'] ?? null,
        );
    }

    public function test_refresh_reports_core_latest_version_when_token_configured(): void
    {
        Env::getRepository()->set('GITHUB_TOKEN', 'test-token');

        Http::fake([
            'api.github.com/repos/nreljed/laravel/releases/latest' => Http::response([
                'tag_name' => 'v0.8.0',
                'published_at' => '2026-08-01T00:00:00Z',
            ], 200),
            'api.github.com/repos/nreljed/platform/releases/latest' => Http::response([
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
            $response->assertJsonPath('core_latest_version.repo', 'nreljed/platform');

            $payload = $response->json();
            $this->assertIsString($payload['latest_version']['checked_at'] ?? null);
            $this->assertIsString($payload['core_latest_version']['checked_at'] ?? null);
        } finally {
            Env::getRepository()->clear('GITHUB_TOKEN');
        }
    }

    public function test_deployed_version_uses_marker_when_present(): void
    {
        $versionFile = config('deploy.version_path');
        File::put($versionFile, 'v9.9.9');

        try {
            $response = $this->actingAs($this->admin())
                ->get(route('dashboard.admin.system-health'));

            $response->assertInertia(fn ($page) => $page
                ->where('deployed_version.exists', true)
                ->where('deployed_version.version', 'v9.9.9')
                ->where('deployed_version.origin', 'deployed')
            );
        } finally {
            File::delete($versionFile);
        }
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

    public function test_measure_tree_skips_deploy_artifacts(): void
    {
        $probe = new class(new CoreVersionService) extends SystemHealthService
        {
            public function probeMeasure(string $path): array
            {
                return $this->measureTree($path);
            }
        };

        $tree = storage_path('app/health-probe-'.getmypid());
        File::makeDirectory($tree, 0755, true);

        try {
            File::put($tree.'/legit.txt', str_repeat('a', 100));
            File::put($tree.'/deploy-'.str_repeat('f', 13).'.zip', str_repeat('b', 2000));
            File::makeDirectory($tree.'/deploy-staging-abc123', 0755, true);
            File::put($tree.'/deploy-staging-abc123/vendor', str_repeat('c', 5000));

            [$size, $count] = $probe->probeMeasure($tree);

            $this->assertSame(1, $count);
            $this->assertSame(100, $size);
        } finally {
            File::deleteDirectory($tree);
        }
    }

    public function test_measure_tree_caps_walk_budget(): void
    {
        $probe = new class(new CoreVersionService) extends SystemHealthService
        {
            public function probeMeasure(string $path): array
            {
                return $this->measureTree(path: $path);
            }
        };

        Config::set('core.health_max_walk_files', 5);

        $tree = storage_path('app/health-probe-'.getmypid());
        File::makeDirectory($tree, 0755, true);

        try {
            for ($i = 1; $i <= 20; $i++) {
                File::put($tree.'/file-'.$i.'.txt', str_repeat('x', 10));
            }

            [$size, $count] = $probe->probeMeasure($tree);

            $this->assertGreaterThanOrEqual(5, $count);
            $this->assertLessThan(20, $count);
            $this->assertSame($count * 10, $size);
        } finally {
            File::deleteDirectory($tree);
            Config::set('core.health_max_walk_files', 5000);
        }
    }
}
