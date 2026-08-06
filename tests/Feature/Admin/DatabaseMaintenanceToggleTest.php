<?php

namespace Tests\Feature\Admin;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Nreljed\DashboardCore\Models\Setting;
use Nreljed\DashboardCore\Services\DatabaseMaintenanceService;
use Nreljed\DashboardCore\Services\SettingsService;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class DatabaseMaintenanceToggleTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Config::set('database_maintenance.enabled', false);
    }

    public function test_admin_can_view_developer_settings_page(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.settings.dev'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/admin/settings/dev')
            ->where('databaseMaintenanceEnabled', false)
            ->where('isProduction', false)
        );
    }

    public function test_enable_database_maintenance_with_correct_password(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.settings.dev.database-maintenance-toggle'), [
                'enabled' => true,
                'password' => 'password',
            ], ['Referer' => route('dashboard.admin.settings.dev')]);

        $response->assertRedirect(route('dashboard.admin.settings.dev'));
        $response->assertSessionHas('status');

        $this->assertSame('1', SettingsService::get('database_maintenance_enabled'));
        $this->assertTrue(app(DatabaseMaintenanceService::class)->isEnabled());

        $page = $this->actingAs($this->admin())->get(route('dashboard.admin.database'));
        $page->assertStatus(200);
    }

    public function test_toggle_rejects_wrong_password(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.settings.dev.database-maintenance-toggle'), [
                'enabled' => true,
                'password' => 'not-the-password',
            ]);

        $response->assertSessionHasErrors('password');
        $this->assertNull(Setting::where('key', 'database_maintenance_enabled')->first());
        $this->assertFalse(app(DatabaseMaintenanceService::class)->isEnabled());
    }

    public function test_toggle_requires_password(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.settings.dev.database-maintenance-toggle'), [
                'enabled' => true,
            ]);

        $response->assertSessionHasErrors('password');
        $this->assertFalse(app(DatabaseMaintenanceService::class)->isEnabled());
    }

    public function test_disable_database_maintenance(): void
    {
        SettingsService::set('database_maintenance_enabled', '1');

        $response = $this->actingAs($this->admin())
            ->post(route('dashboard.admin.settings.dev.database-maintenance-toggle'), [
                'enabled' => false,
                'password' => 'password',
            ], ['Referer' => route('dashboard.admin.settings.dev')]);

        $response->assertRedirect(route('dashboard.admin.settings.dev'));
        $response->assertSessionHas('status');

        $this->assertSame('0', SettingsService::get('database_maintenance_enabled'));
        $this->assertFalse(app(DatabaseMaintenanceService::class)->isEnabled());

        $page = $this->actingAs($this->admin())->get(route('dashboard.admin.database'));
        $page->assertStatus(403);
    }

    public function test_non_admin_cannot_toggle(): void
    {
        $response = $this->actingAs($this->regularUser())
            ->post(route('dashboard.admin.settings.dev.database-maintenance-toggle'), [
                'enabled' => true,
                'password' => 'password',
            ]);

        $response->assertStatus(403);
        $this->assertFalse(app(DatabaseMaintenanceService::class)->isEnabled());
    }
}
