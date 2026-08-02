<?php

namespace Tests\Feature\Security;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Nreljed\DashboardCore\Models\Setting;
use Nreljed\DashboardCore\Services\SettingsService;
use Tests\TestCase;

class PasswordComplexityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_registration_accepts_password_meeting_complexity(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->setSecuritySetting('password_require_uppercase', '1');
        $this->setSecuritySetting('password_require_numbers', '1');
        $this->setSecuritySetting('password_require_special', '1');

        $response = $this->post('/register', [
            'username' => 'complexuser',
            'email' => 'complex@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', ['email' => 'complex@example.com']);
    }

    public function test_registration_rejects_password_without_uppercase(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->setSecuritySetting('password_require_uppercase', '1');

        $response = $this->post('/register', [
            'username' => 'noupper',
            'email' => 'noupper@example.com',
            'password' => 'password1!',
            'password_confirmation' => 'password1!',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'noupper@example.com']);
    }

    public function test_registration_rejects_password_without_number(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->setSecuritySetting('password_require_numbers', '1');

        $response = $this->post('/register', [
            'username' => 'nonumber',
            'email' => 'nonumber@example.com',
            'password' => 'PasswordTest!',
            'password_confirmation' => 'PasswordTest!',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'nonumber@example.com']);
    }

    public function test_registration_rejects_password_without_special_char(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->setSecuritySetting('password_require_special', '1');

        $response = $this->post('/register', [
            'username' => 'nospecial',
            'email' => 'nospecial@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'nospecial@example.com']);
    }

    public function test_registration_accepts_simple_password_when_complexity_disabled(): void
    {
        if (! $this->hasSettingsTable()) {
            $this->markTestSkipped('Settings table not available.');
        }

        $this->setSecuritySetting('password_require_uppercase', '0');
        $this->setSecuritySetting('password_require_numbers', '0');
        $this->setSecuritySetting('password_require_special', '0');

        $response = $this->post('/register', [
            'username' => 'simplepass',
            'email' => 'simplepass@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', ['email' => 'simplepass@example.com']);
    }

    protected function setSecuritySetting(string $key, string $value): void
    {
        Setting::firstOrCreate(
            ['key' => $key],
            ['key' => $key, 'value' => $value, 'group' => 'security'],
        );
        Setting::where('key', $key)->update(['value' => $value]);
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
