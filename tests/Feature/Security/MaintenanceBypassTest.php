<?php

namespace Tests\Feature\Security;

use App\Models\Setting;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class MaintenanceBypassTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_bypass_token_allows_access_during_maintenance(): void
    {
        $token = Str::random(64);
        Setting::create(['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance']);
        Setting::create(['key' => 'maintenance_bypass_token', 'value' => $token, 'group' => 'maintenance']);
        SettingsService::clearCache();

        $response = $this->get("/?token={$token}");
        $response->assertStatus(200);
    }

    public function test_invalid_token_is_rejected_during_maintenance(): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance']);
        Setting::create(['key' => 'maintenance_bypass_token', 'value' => 'real-token', 'group' => 'maintenance']);
        SettingsService::clearCache();

        $response = $this->get('/?token=wrong-token');
        $response->assertStatus(503);
    }

    public function test_no_token_returns_503_during_maintenance(): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance']);
        Setting::create(['key' => 'maintenance_bypass_token', 'value' => 'some-token', 'group' => 'maintenance']);
        SettingsService::clearCache();

        $response = $this->get('/');
        $response->assertStatus(503);
    }

    public function test_bypass_token_generates_when_maintenance_enabled(): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '0', 'group' => 'maintenance']);
        SettingsService::clearCache();

        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->put(route('dashboard.admin.settings.update'), [
            'maintenance_mode' => '1',
            'maintenance_message' => 'Down for maintenance',
        ]);

        $token = SettingsService::get('maintenance_bypass_token', '');
        $this->assertNotEmpty($token);
    }

    public function test_bypass_token_cleared_when_maintenance_disabled(): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance']);
        Setting::create(['key' => 'maintenance_bypass_token', 'value' => 'some-token', 'group' => 'maintenance']);
        SettingsService::clearCache();

        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->put(route('dashboard.admin.settings.update'), [
            'maintenance_mode' => '0',
            'maintenance_message' => 'Down for maintenance',
        ]);

        $token = SettingsService::get('maintenance_bypass_token', '');
        $this->assertEmpty($token);
    }

    public function test_check_availability_returns_503_during_maintenance(): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance']);
        SettingsService::clearCache();

        $response = $this->getJson('/register/check-availability?email=test@example.com');
        $response->assertStatus(503);
    }

    public function test_bypass_cookie_route_sets_cookie_and_redirects(): void
    {
        $token = Str::random(64);
        Setting::create(['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance']);
        Setting::create(['key' => 'maintenance_bypass_token', 'value' => $token, 'group' => 'maintenance']);
        SettingsService::clearCache();

        $response = $this->get("/maintenance/bypass/{$token}");
        $response->assertRedirect('/');
        $response->assertCookie('maintenance_bypass', $token);
    }

    public function test_bypass_cookie_route_rejects_invalid_token(): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance']);
        Setting::create(['key' => 'maintenance_bypass_token', 'value' => 'real-token', 'group' => 'maintenance']);
        SettingsService::clearCache();

        $response = $this->get('/maintenance/bypass/wrong-token');
        $response->assertStatus(404);
    }

    public function test_bypass_cookie_route_rejects_when_no_token_configured(): void
    {
        Setting::create(['key' => 'maintenance_mode', 'value' => '1', 'group' => 'maintenance']);
        Setting::create(['key' => 'maintenance_bypass_token', 'value' => '', 'group' => 'maintenance']);
        SettingsService::clearCache();

        $response = $this->get('/maintenance/bypass/some-token');
        $response->assertStatus(404);
    }
}
