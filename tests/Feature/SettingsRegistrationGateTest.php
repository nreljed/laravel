<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Services\SettingsService;
use Tests\TestCase;

class SettingsRegistrationGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_registration_works_when_enabled(): void
    {
        $response = $this->post('/register', [
            'username' => 'newuser',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard.index', absolute: false));
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_registration_is_blocked_when_disabled(): void
    {
        if (! $this->canSetSetting()) {
            $this->markTestSkipped('Settings table not available.');
        }

        SettingsService::set('enable_registration', '0');
        SettingsService::clearCache();

        $response = $this->post('/register', [
            'username' => 'blockeduser',
            'email' => 'blocked@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }

    public function test_registration_page_shows_closed_when_disabled(): void
    {
        if (! $this->canSetSetting()) {
            $this->markTestSkipped('Settings table not available.');
        }

        SettingsService::set('enable_registration', '0');
        SettingsService::clearCache();

        $response = $this->get('/register');
        $response->assertOk();
    }

    public function test_registration_page_loads_when_enabled(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
    }

    public function test_registration_settings_page_does_not_write_default_role(): void
    {
        if (! $this->canSetSetting()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'admin')->first());

        $this->actingAs($admin)
            ->get(route('dashboard.admin.settings.registration'))
            ->assertOk();

        $this->assertDatabaseMissing('settings', ['key' => 'default_role']);
    }

    protected function canSetSetting(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
