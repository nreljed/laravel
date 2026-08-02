<?php

namespace Tests\Feature\Deploy;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nreljed\DashboardCore\Models\User;
use Tests\TestCase;

class DeployEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = 'test-deploy-token-'.Str::random(16);
        Config::set('app.deploy_token', $this->token);
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
        $response->assertJsonStructure(['output' => ['migrate:fresh', 'cache:clear', 'app:create-admin']]);
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
        $lockFile = base_path('.deploy-lock');
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
}
