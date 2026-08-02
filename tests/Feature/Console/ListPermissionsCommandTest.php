<?php

namespace Tests\Feature\Console;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListPermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_lists_all_permissions(): void
    {
        $this->artisan('permissions:list')
            ->expectsOutputToContain('posts.view')
            ->expectsOutputToContain('Total: ')
            ->assertSuccessful();
    }

    public function test_lists_permissions_as_json(): void
    {
        $this->artisan('permissions:list', ['--format' => 'json'])
            ->expectsOutputToContain('"posts.view"')
            ->assertSuccessful();
    }

    public function test_filters_by_role(): void
    {
        $this->artisan('permissions:list', ['--role' => 'admin'])
            ->expectsOutputToContain('Permissions for role:')
            ->expectsOutputToContain('manage-options')
            ->assertSuccessful();
    }

    public function test_unknown_role_returns_failure(): void
    {
        $this->artisan('permissions:list', ['--role' => 'does-not-exist'])
            ->expectsOutputToContain("Role 'does-not-exist' not found.")
            ->assertFailed();
    }

    public function test_filters_by_group(): void
    {
        $this->artisan('permissions:list', ['--group' => 'posts'])
            ->expectsOutputToContain('posts.view')
            ->expectsOutputToContain('posts.create')
            ->assertSuccessful();
    }
}
