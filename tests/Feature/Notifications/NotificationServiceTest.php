<?php

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Models\NotificationPreference;
use Nreljed\Platform\Notifications\AppNotification;
use Nreljed\Platform\Services\NotificationService;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_get_cached_unread_count_returns_correct_count(): void
    {
        $user = $this->regularUser();

        $this->createNotificationsFor($user, 3);

        $service = app(NotificationService::class);
        $count = $service->getCachedUnreadCount($user->id, $user::class);

        $this->assertEquals(3, $count);
    }

    public function test_invalidate_unread_count_clears_cache(): void
    {
        $user = $this->regularUser();

        $this->createNotificationsFor($user, 3);

        $service = app(NotificationService::class);

        $countBefore = $service->getCachedUnreadCount($user->id, $user::class);
        $this->assertEquals(3, $countBefore);

        $service->invalidateUnreadCache($user->id);

        $this->createNotificationsFor($user, 1);

        $countAfter = $service->getCachedUnreadCount($user->id, $user::class);
        $this->assertEquals(4, $countAfter);
    }

    public function test_purge_removes_old_notifications(): void
    {
        $user = $this->regularUser();

        $this->createNotificationsFor($user, 1);

        $oldId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $oldId,
            'type' => 'Nreljed\Platform\Notifications\TestNotification',
            'notifiable_id' => $user->id,
            'notifiable_type' => $user::class,
            'data' => json_encode(['title' => 'Old notification', 'message' => 'Old']),
            'created_at' => now()->subDays(100),
        ]);

        $service = app(NotificationService::class);
        $deleted = $service->purge(90);

        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertDatabaseMissing('notifications', ['id' => $oldId]);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_notify_via_preferences_respects_disabled_in_app_preference(): void
    {
        $user = $this->regularUser();

        NotificationPreference::create([
            'user_id' => $user->id,
            'type' => 'post.published',
            'channel' => 'in_app',
            'enabled' => false,
        ]);

        $service = app(NotificationService::class);
        $service->notifyViaPreferences(
            $user,
            'post.published',
            new AppNotification('post.published', ['title' => 'Skipped']),
            ['in_app']
        );

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notify_via_preferences_creates_notification_when_enabled_and_invalidates_unread_cache(): void
    {
        $user = $this->regularUser();

        $service = app(NotificationService::class);
        $service->notifyViaPreferences(
            $user,
            'post.published',
            new AppNotification('post.published', ['title' => 'Hello']),
            ['in_app']
        );

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(1, $service->getCachedUnreadCount($user->id, $user::class));
    }

    protected function createNotificationsFor($user, int $count): array
    {
        $notifications = [];

        for ($i = 0; $i < $count; $i++) {
            $notifications[] = $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'Nreljed\Platform\Notifications\TestNotification',
                'data' => ['title' => 'Test', 'message' => 'Test message '.$i],
            ]);
        }

        return $notifications;
    }
}
