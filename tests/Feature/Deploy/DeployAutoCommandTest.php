<?php

namespace Tests\Feature\Deploy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeployAutoCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $lockPath;

    protected string $versionPath;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('deploy.github_repo', 'nreljed/laravel');

        $this->lockPath = base_path('.deploy-lock-'.getmypid());
        $this->versionPath = base_path('.deployed-version-'.getmypid());
        Config::set('deploy.lock_path', $this->lockPath);
        Config::set('deploy.version_path', $this->versionPath);
    }

    protected function tearDown(): void
    {
        File::delete($this->lockPath);
        File::delete($this->versionPath);
        parent::tearDown();
    }

    public function test_command_is_registered(): void
    {
        $this->assertArrayHasKey('deploy:auto', Artisan::all());
    }

    public function test_skips_when_deploy_lock_is_fresh(): void
    {
        File::put($this->lockPath, 'test-pid');
        touch($this->lockPath, now()->subSeconds(30)->timestamp);

        $this->artisan('deploy:auto')
            ->expectsOutputToContain('already in progress')
            ->assertExitCode(0);
    }

    public function test_reports_up_to_date_when_version_matches(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                ['tag_name' => 'v1.2.3', 'assets' => []],
            ]),
        ]);

        File::put($this->versionPath, 'v1.2.3');

        $this->artisan('deploy:auto')
            ->expectsOutputToContain('Already up to date (v1.2.3)')
            ->assertExitCode(0);
    }

    public function test_reports_no_release_when_github_api_fails(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([], 500),
        ]);

        $this->artisan('deploy:auto')
            ->expectsOutputToContain('No release to deploy')
            ->assertExitCode(0);
    }

    public function test_fails_when_new_release_has_no_zip_asset(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                ['tag_name' => 'v9.9.9', 'assets' => []],
            ]),
        ]);

        $this->artisan('deploy:auto')
            ->expectsOutputToContain('No deploy.zip in release')
            ->assertExitCode(1);
    }

    public function test_fails_when_zip_download_is_empty(): void
    {
        Http::fake([
            'api.github.com/repos/*/releases*' => Http::response([
                [
                    'tag_name' => 'v9.9.9',
                    'assets' => [
                        ['name' => 'deploy.zip', 'url' => 'https://api.github.com/repos/nreljed/laravel/assets/123'],
                    ],
                ],
            ]),
            'api.github.com/repos/*/assets/*' => Http::response('tiny', 200),
        ]);

        $this->artisan('deploy:auto')
            ->expectsOutputToContain('Deploy failed: Download failed')
            ->assertExitCode(1);
    }
}
