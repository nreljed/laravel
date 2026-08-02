<?php

namespace Tests\Feature\Maintenance;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Nreljed\DashboardCore\Models\Setting;
use Nreljed\DashboardCore\Services\SettingsService;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_public_pages_accessible_when_maintenance_off(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_non_admin_sees_503_when_maintenance_enabled(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();

        $response = $this->get('/');
        $response->assertStatus(503);
        $response->assertSee('Under Maintenance');
    }

    public function test_admin_bypasses_maintenance_mode(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();

        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_regular_user_blocked_from_dashboard_during_maintenance(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();

        $user = $this->regularUser();
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(503);
    }

    public function test_login_blocked_during_maintenance(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();

        $response = $this->get('/login');
        $response->assertStatus(503);
    }

    public function test_register_blocked_during_maintenance(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();

        $response = $this->get('/register');
        $response->assertStatus(503);
    }

    public function test_check_availability_blocked_during_maintenance(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();

        $response = $this->getJson('/register/check-availability?email=test@example.com');
        $response->assertStatus(503);
    }

    public function test_admin_settings_accessible_during_maintenance(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();

        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($admin)->get('/dashboard/admin/settings/general');
        $response->assertStatus(200);
    }

    public function test_maintenance_message_is_displayed(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode('Custom maintenance message here.');

        $response = $this->get('/');
        $response->assertStatus(503);
        $response->assertSee('Custom maintenance message here.');
    }

    public function test_default_maintenance_message_is_displayed(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();

        $response = $this->get('/');
        $response->assertStatus(503);
        $response->assertSee('We are performing scheduled maintenance. Please check back shortly.');
    }

    public function test_disabling_maintenance_restores_access(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();

        $response = $this->get('/');
        $response->assertStatus(503);

        SettingsService::set('maintenance_mode', '0');
        SettingsService::clearCache();

        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_set_many_cache_invalidation_clears_individual_keys(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        SettingsService::set('maintenance_mode', '0');
        SettingsService::clearCache();

        $value = SettingsService::get('maintenance_mode');
        $this->assertEquals('0', $value);

        SettingsService::setMany(['maintenance_mode' => '1']);

        $value = SettingsService::get('maintenance_mode');
        $this->assertEquals('1', $value);
    }

    public function test_503_includes_retry_after_header(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();

        $response = $this->get('/');
        $response->assertStatus(503);
        $response->assertHeader('Retry-After');
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    public function test_503_retry_after_uses_ends_at_when_set(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();
        $endsAt = now()->addMinutes(45);
        SettingsService::set('maintenance_ends_at', $endsAt->toIso8601String());
        SettingsService::clearCache();

        $response = $this->get('/');
        $response->assertStatus(503);
        $retryAfter = (int) $response->headers->get('Retry-After');
        $this->assertGreaterThan(2500, $retryAfter);
        $this->assertLessThanOrEqual(2800, $retryAfter);
    }

    public function test_maintenance_page_includes_countdown_when_ends_at_set(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();
        $endsAt = now()->addHours(2);
        SettingsService::set('maintenance_ends_at', $endsAt->toIso8601String());
        SettingsService::clearCache();

        $response = $this->get('/');
        $response->assertStatus(503);
        $response->assertSee('countdown');
        $response->assertSee('cd-hours');
    }

    public function test_maintenance_page_no_countdown_when_no_ends_at(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();
        SettingsService::set('maintenance_ends_at', '');
        SettingsService::clearCache();

        $response = $this->get('/');
        $response->assertStatus(503);
        $response->assertSee('Please check back later');
        $response->assertDontSee('cd-hours');
    }

    public function test_maintenance_status_endpoint_returns_json(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        SettingsService::set('maintenance_mode', '0');
        SettingsService::clearCache();

        $response = $this->getJson('/maintenance/status');
        $response->assertStatus(200);
        $response->assertJson(['maintenance' => false]);
        $response->assertHeader('Cache-Control');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
    }

    public function test_maintenance_status_endpoint_true_when_enabled(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();

        $response = $this->getJson('/maintenance/status');
        $response->assertStatus(200);
        $response->assertJson(['maintenance' => true]);
    }

    public function test_maintenance_status_returns_ends_at(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->enableMaintenanceMode();
        $endsAt = '2026-12-31T23:59:59.000000Z';
        SettingsService::set('maintenance_ends_at', $endsAt);
        SettingsService::clearCache();

        $response = $this->getJson('/maintenance/status');
        $response->assertStatus(200);
        $response->assertJson(['endsAt' => $endsAt]);
    }

    protected function enableMaintenanceMode(?string $message = null): void
    {
        Setting::firstOrCreate(
            ['key' => 'maintenance_mode'],
            ['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance'],
        );
        Setting::where('key', 'maintenance_mode')->update(['value' => '1']);

        if ($message !== null) {
            Setting::firstOrCreate(
                ['key' => 'maintenance_message'],
                ['key' => 'maintenance_message', 'value' => $message, 'group' => 'maintenance'],
            );
            Setting::where('key', 'maintenance_message')->update(['value' => $message]);
        }

        SettingsService::clearCache();
    }

    protected function hasSettingsTable(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
