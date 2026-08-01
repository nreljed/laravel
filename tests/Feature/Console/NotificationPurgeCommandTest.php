<?php

namespace Tests\Feature\Console;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class NotificationPurgeCommandTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_purges_notifications_older_than_retention_days(): void
    {
        $user = $this->regularUser();

        $old = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'data' => ['message' => 'Old'],
            'created_at' => now()->subDays(100),
        ]);
        $recent = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'data' => ['message' => 'Recent'],
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('notifications:purge', ['--days' => 90])
            ->expectsOutputToContain('Purged 1 notifications older than 90 days.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $old->id]);
        $this->assertDatabaseHas('notifications', ['id' => $recent->id]);
    }

    public function test_custom_retention_days_option(): void
    {
        $user = $this->regularUser();

        $old = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'data' => ['message' => 'Old'],
            'created_at' => now()->subDays(30),
        ]);

        $this->artisan('notifications:purge', ['--days' => 7])
            ->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $old->id]);
    }
}
