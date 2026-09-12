<?php

namespace Tests\Feature\Notifications;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class NotificationPollTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $this->get(route('api.notifications.unread-count'))
            ->assertStatus(401);
    }

    public function test_user_can_get_unread_count(): void
    {
        $user = $this->regularUser();

        $this->createNotificationsFor($user, 3);

        $response = $this->actingAs($user)->getJson(route('api.notifications.unread-count'));

        $response->assertOk();
        $response->assertJsonPath('count', 3);
    }

    public function test_user_can_get_recent_notifications(): void
    {
        $user = $this->regularUser();

        $this->createNotificationsFor($user, 3);

        $response = $this->actingAs($user)->getJson(route('api.notifications.recent'));

        $response->assertOk();
        $response->assertJsonStructure([
            'notifications' => [
                '*' => ['id', 'type', 'title', 'message', 'is_read', 'created_at'],
            ],
            'unread_count',
        ]);
        $response->assertJsonCount(3, 'notifications');
    }

    public function test_user_can_list_notifications(): void
    {
        $user = $this->regularUser();

        $this->createNotificationsFor($user, 2);

        $response = $this->actingAs($user)->getJson(route('api.notifications.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'notifications',
            'total',
            'unread_count',
            'limit',
            'offset',
        ]);
        $response->assertJsonPath('total', 2);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = $this->regularUser();

        $notification = $this->createNotificationsFor($user, 1)[0];

        $response = $this->actingAs($user)->postJson(route('api.notifications.mark-read'), [
            'id' => $notification->id,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_as_read(): void
    {
        $user = $this->regularUser();

        $notifications = $this->createNotificationsFor($user, 3);

        $response = $this->actingAs($user)->postJson(route('api.notifications.mark-all-read'));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        foreach ($notifications as $n) {
            $this->assertNotNull($n->fresh()->read_at);
        }
    }

    public function test_user_can_delete_notification(): void
    {
        $user = $this->regularUser();

        $notification = $this->createNotificationsFor($user, 1)[0];

        $response = $this->actingAs($user)->deleteJson(
            route('api.notifications.destroy', $notification->id)
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_notifications_are_scoped_to_user(): void
    {
        $userA = $this->regularUser();
        $userB = $this->regularUser();

        $this->createNotificationsFor($userA, 3);

        $response = $this->actingAs($userB)->getJson(route('api.notifications.index'));

        $response->assertOk();
        $response->assertJsonPath('total', 0);
        $response->assertJsonCount(0, 'notifications');
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
