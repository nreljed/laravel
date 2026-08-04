<?php

namespace Tests\Feature\Admin;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Nreljed\DashboardCore\Exceptions\DatabaseMaintenanceException;
use Nreljed\DashboardCore\Services\DatabaseMaintenanceService;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class DatabaseMaintenanceTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('database_maintenance.enabled', true);
    }

    public function test_admin_can_view_database_maintenance_page(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $service->shouldReceive('isEnabled')->andReturn(true);
        $service->shouldReceive('status')->andReturn([
            'enabled' => true,
            'environment' => 'testing',
            'is_production' => false,
            'database_driver' => 'sqlite',
            'table_count' => 12,
            'locked' => false,
            'lock_path' => base_path('.maintenance-lock'),
            'media_disk_path' => storage_path('app/public'),
            'last_reset_at' => null,
            'last_reset_by' => null,
        ]);
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.database'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/admin/database-maintenance')
            ->has('status', fn ($status) => $status
                ->where('enabled', true)
                ->where('environment', 'testing')
                ->where('table_count', 12)
                ->where('locked', false)
                ->etc()
            )
        );
    }

    public function test_non_admin_cannot_view_database_maintenance_page(): void
    {
        $response = $this->actingAs($this->regularUser())
            ->get(route('dashboard.admin.database'));

        $response->assertStatus(403);
    }

    public function test_page_returns_403_when_feature_disabled(): void
    {
        Config::set('database_maintenance.enabled', false);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.database'));

        $response->assertStatus(403);
    }

    public function test_reset_returns_403_when_feature_disabled(): void
    {
        Config::set('database_maintenance.enabled', false);

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.database.reset'), [
                'confirmation' => 'RESET',
                'password' => 'password',
            ]);

        $response->assertStatus(403);
    }

    public function test_reset_rejects_missing_confirmation(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $service->shouldReceive('isEnabled')->andReturn(true);
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.database.reset'), [
                'password' => 'password',
            ]);

        $response->assertSessionHasErrors('confirmation');
        $service->shouldNotHaveReceived('resetDatabase');
    }

    public function test_reset_rejects_wrong_password(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $service->shouldReceive('isEnabled')->andReturn(true);
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.database.reset'), [
                'confirmation' => 'RESET',
                'password' => 'not-the-password',
            ]);

        $response->assertSessionHasErrors('password');
        $service->shouldNotHaveReceived('resetDatabase');
    }

    public function test_reset_runs_service_and_logs_out(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $service->shouldReceive('isEnabled')->andReturn(true);
        $service->shouldReceive('resetDatabase')
            ->once()
            ->withArgs(fn (bool $withSeed, bool $purgeMedia, ?int $userId) => $withSeed === true && $purgeMedia === false && $userId > 0)
            ->andReturn(['success' => true, 'output' => []]);
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.database.reset'), [
                'confirmation' => 'RESET',
                'password' => 'password',
            ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reset_passes_purge_media_flag(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $service->shouldReceive('isEnabled')->andReturn(true);
        $service->shouldReceive('resetDatabase')
            ->once()
            ->withArgs(fn (bool $withSeed, bool $purgeMedia, ?int $userId) => $withSeed === true && $purgeMedia === true && $userId > 0)
            ->andReturn(['success' => true, 'output' => []]);
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.database.reset'), [
                'confirmation' => 'RESET',
                'password' => 'password',
                'purge_media' => true,
            ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reset_redirects_to_login_on_failure(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $service->shouldReceive('isEnabled')->andReturn(true);
        $service->shouldReceive('resetDatabase')
            ->once()
            ->andThrow(new DatabaseMaintenanceException('Database reset failed.'));
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.database.reset'), [
                'confirmation' => 'RESET',
                'password' => 'password',
            ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('flash.error');
        $this->assertGuest();
    }

    public function test_reseed_rejects_missing_confirmation(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $service->shouldReceive('isEnabled')->andReturn(true);
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.database.reseed'), [
                'password' => 'password',
            ]);

        $response->assertSessionHasErrors('confirmation');
        $service->shouldNotHaveReceived('resetDatabase');
    }

    public function test_reseed_runs_service_and_redirects_back(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $service->shouldReceive('isEnabled')->andReturn(true);
        $service->shouldReceive('resetDatabase')
            ->once()
            ->withArgs(fn (bool $withSeed, bool $purgeMedia) => $withSeed === false && $purgeMedia === false)
            ->andReturn(['success' => true, 'output' => []]);
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->actingAs($this->admin())
            ->post(
                route('dashboard.admin.database.reseed'),
                [
                    'confirmation' => 'RESEED',
                    'password' => 'password',
                ],
                ['Referer' => route('dashboard.admin.database')],
            );

        $response->assertRedirect(route('dashboard.admin.database'));
        $response->assertSessionHas('flash.success');
    }

    public function test_reseed_redirects_back_on_failure(): void
    {
        $service = Mockery::mock(DatabaseMaintenanceService::class);
        $service->shouldReceive('isEnabled')->andReturn(true);
        $service->shouldReceive('resetDatabase')
            ->once()
            ->andThrow(new DatabaseMaintenanceException('Database reseed failed.'));
        $this->instance(DatabaseMaintenanceService::class, $service);

        $response = $this->actingAs($this->admin())
            ->post(
                route('dashboard.admin.database.reseed'),
                [
                    'confirmation' => 'RESEED',
                    'password' => 'password',
                ],
                ['Referer' => route('dashboard.admin.database')],
            );

        $response->assertRedirect(route('dashboard.admin.database'));
        $response->assertSessionHas('flash.error');
    }
}
