<?php

namespace Tests\Feature\Deploy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Nreljed\Platform\Exceptions\DatabaseMaintenanceException;
use Nreljed\Platform\Services\DatabaseMaintenanceService;
use Tests\TestCase;

class DatabaseResetDeployEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = 'test-reset-token';
        Config::set('deploy.token', $this->token);
    }

    public function test_reset_requires_token(): void
    {
        $response = $this->postJson('/deploy/reset', ['confirm' => 'RESET']);

        $response->assertStatus(403);
    }

    public function test_reset_rejects_invalid_token(): void
    {
        $response = $this->postJson('/deploy/reset', [
            'token' => 'wrong-token',
            'confirm' => 'RESET',
        ]);

        $response->assertStatus(403);
    }

    public function test_reset_requires_confirmation(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->postJson('/deploy/reset', ['token' => $this->token]);

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
        $service->shouldNotHaveReceived('resetDatabase');
    }

    public function test_reset_rejects_wrong_confirmation(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->postJson('/deploy/reset', [
            'token' => $this->token,
            'confirm' => 'RESEED',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
        $service->shouldNotHaveReceived('resetDatabase');
    }

    public function test_reset_accepts_valid_confirmation(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $service->shouldReceive('resetDatabase')
            ->once()
            ->withArgs(fn (bool $purgeMedia, ?int $userId, bool $demo) => $purgeMedia === false && $userId === 0 && $demo === false)
            ->andReturn(['success' => true, 'output' => ['migrate:fresh: ok']]);
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->postJson('/deploy/reset', [
            'token' => $this->token,
            'confirm' => 'RESET',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['output']);
    }

    public function test_reset_passes_demo_and_purge_flags(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $service->shouldReceive('resetDatabase')
            ->once()
            ->withArgs(fn (bool $purgeMedia, ?int $userId, bool $demo) => $purgeMedia === true && $userId === 0 && $demo === true)
            ->andReturn(['success' => true, 'output' => []]);
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->postJson('/deploy/reset', [
            'token' => $this->token,
            'confirm' => 'RESET',
            'with_demo' => true,
            'purge_media' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
    }

    public function test_reset_returns_500_on_service_failure(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $service->shouldReceive('resetDatabase')
            ->once()
            ->andThrow(new DatabaseMaintenanceException('Another operation is already running.'));
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->postJson('/deploy/reset', [
            'token' => $this->token,
            'confirm' => 'RESET',
        ]);

        $response->assertStatus(500);
        $response->assertJsonPath('status', 'error');
        $response->assertJsonPath('error', 'Another operation is already running.');
    }
}
