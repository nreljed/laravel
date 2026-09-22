<?php

namespace Tests\Feature\Deploy;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Models\User;
use Tests\TestCase;

class DeployEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = 'test-deploy-token-'.Str::random(16);
        Config::set('deploy.token', $this->token);
        Config::set('deploy.lock_path', base_path('.deploy-lock-'.getmypid()));
        Config::set('deploy.version_path', base_path('.deployed-version-'.getmypid()));
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_upload_requires_token(): void
    {
        $response = $this->postJson('/deploy/upload');
        $response->assertStatus(403);
    }

    public function test_upload_rejects_invalid_token(): void
    {
        $response = $this->postJson('/deploy/upload', ['token' => 'wrong-token']);
        $response->assertStatus(403);
    }

    public function test_upload_requires_zip_file(): void
    {
        $response = $this->postJson('/deploy/upload', ['token' => $this->token]);
        $response->assertStatus(400);
    }

    public function test_cache_clear_requires_token(): void
    {
        $response = $this->postJson('/deploy/cache-clear');
        $response->assertStatus(403);
    }

    public function test_cache_clear_accepts_valid_token(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->method('call')->willReturn(0);
        $kernel->method('output')->willReturn('ok');
        Artisan::swap($kernel);

        $files = $this->createMock(Filesystem::class);
        $files->method('exists')->willReturn(false);
        $files->method('put')->willReturn(true);
        $files->method('get')->willReturn('');
        $files->method('glob')->willReturn([]);
        $files->method('delete')->willReturn(true);
        File::swap($files);

        $response = $this->postJson('/deploy/cache-clear', ['token' => $this->token]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['output' => ['migrate', 'cache:clear', 'app:create-admin']]);
    }

    public function test_check_requires_token(): void
    {
        $response = $this->postJson('/deploy/check');
        $response->assertStatus(403);
    }

    public function test_check_rejects_invalid_token(): void
    {
        $response = $this->postJson('/deploy/check', ['token' => 'wrong-token']);
        $response->assertStatus(403);
    }

    public function test_upload_rejects_non_zip_extension_with_valid_token(): void
    {
        $file = UploadedFile::fake()->create('deploy.tar.gz', 1024);

        $response = $this->postJson('/deploy/upload', [
            'token' => $this->token,
            'file' => $file,
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'File must be a zip']);
    }

    public function test_check_returns_locked_when_lock_file_is_fresh(): void
    {
        $lockFile = config('deploy.lock_path');
        File::put($lockFile, 'test-pid');
        touch($lockFile, now()->subSeconds(60)->timestamp);

        try {
            $response = $this->postJson('/deploy/check', ['token' => $this->token]);

            $response->assertOk();
            $response->assertJson(['status' => 'locked']);
        } finally {
            File::delete($lockFile);
        }
    }

    public function test_check_returns_current_when_version_matches(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                ['tag_name' => 'v1.2.3', 'assets' => []],
            ]),
        ]);

        $versionFile = config('deploy.version_path');
        File::put($versionFile, 'v1.2.3');

        try {
            $response = $this->postJson('/deploy/check', ['token' => $this->token]);

            $response->assertOk();
            $response->assertJson([
                'status' => 'current',
                'tag' => 'v1.2.3',
            ]);
            // Regression: /deploy/check must surface the bundled SBOM block
            // (DeployController dropped it until v0.1.60).
            $response->assertJsonStructure([
                'status',
                'bundled' => ['*' => ['name', 'version', 'sha', 'repo']],
            ]);
            $bundled = collect($response->json('bundled'))->keyBy('name');
            $this->assertTrue($bundled->has('nreljed/platform'), 'bundled SBOM must list nreljed/platform');
            $this->assertSame('nreljed/platform', $bundled['nreljed/platform']['repo']);
            $this->assertNotNull($bundled['nreljed/platform']['sha']);
        } finally {
            File::delete($versionFile);
            File::delete(config('deploy.lock_path'));
        }
    }

    public function test_create_admin_rejects_short_password(): void
    {
        $response = $this->postJson('/deploy/create-admin', [
            'token' => $this->token,
            'password' => 'short',
        ]);

        $response->assertStatus(400);
    }

    public function test_create_admin_creates_admin_with_valid_token(): void
    {
        $email = 'deploy-admin-'.Str::random(6).'@example.com';
        $password = 'StrongPassword123!';

        $response = $this->postJson('/deploy/create-admin', [
            'token' => $this->token,
            'email' => $email,
            'password' => $password,
            'name' => 'Deploy Admin',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');

        $admin = User::where('email', $email)->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isAdmin());
    }

    public function test_env_values_rejects_reserved_keys(): void
    {
        $response = $this->postJson('/deploy/env', [
            'token' => $this->token,
            'values' => ['APP_DEBUG' => 'true'],
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Setting APP_DEBUG cannot be modified via this endpoint.']);
    }

    public function test_env_values_rejects_session_lifetime_key(): void
    {
        $response = $this->postJson('/deploy/env', [
            'token' => $this->token,
            'values' => ['SESSION_LIFETIME' => '1'],
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Setting SESSION_LIFETIME cannot be modified via this endpoint.']);
    }
}
