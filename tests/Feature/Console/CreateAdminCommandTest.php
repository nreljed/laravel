<?php

namespace Tests\Feature\Console;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Models\User;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_creates_admin_user_with_credentials(): void
    {
        $this->artisan('app:create-admin', [
            '--email' => 'admin@example.com',
            '--password' => 'SecretPassword123!',
            '--name' => 'Site Admin',
        ])
            ->expectsOutputToContain('Admin user created: admin@example.com')
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(Hash::check('SecretPassword123!', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_updates_existing_admin_when_password_provided(): void
    {
        User::create([
            'name' => 'Old Admin',
            'email' => config('admin.email'),
            'password' => 'OldPassword123!',
        ]);

        $this->artisan('app:create-admin', [
            '--password' => 'NewPassword123!',
            '--name' => 'Updated Admin',
        ])
            ->expectsOutputToContain('Admin user updated:')
            ->assertSuccessful();

        $user = User::where('email', config('admin.email'))->first();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_skips_existing_admin_without_password(): void
    {
        Config::set('admin.password', null);

        User::create([
            'name' => 'Existing Admin',
            'email' => config('admin.email'),
            'password' => 'ExistingPassword123!',
        ]);

        $this->artisan('app:create-admin')
            ->expectsOutputToContain('already exists, skipping.')
            ->assertSuccessful();
    }

    public function test_fails_when_no_password_provided(): void
    {
        Config::set('admin.password', null);

        $this->artisan('app:create-admin', [
            '--email' => 'new-admin@example.com',
        ])
            ->expectsOutputToContain('Password is required.')
            ->assertFailed();
    }

    public function test_fails_when_admin_role_does_not_exist(): void
    {
        Role::where('slug', 'admin')->delete();

        $this->artisan('app:create-admin', [
            '--email' => 'admin@example.com',
            '--password' => 'SecretPassword123!',
            '--name' => 'Site Admin',
        ])
            ->expectsOutputToContain('admin role not found')
            ->assertFailed();
    }
}
