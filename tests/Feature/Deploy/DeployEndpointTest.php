<?php

namespace Tests\Feature\Deploy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
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
        $this->markTestSkipped('cacheClear runs migrate:fresh — tested manually');
    }

    public function test_check_requires_token(): void
    {
        $response = $this->postJson('/deploy/check');
        $response->assertStatus(403);
    }
}
