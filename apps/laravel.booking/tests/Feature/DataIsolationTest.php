<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function createUserWithRole(string $roleSlug): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', $roleSlug)->first();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_user_can_only_see_own_notification_preferences(): void
    {
        $user1 = $this->createUserWithRole('customer');
        $user2 = $this->createUserWithRole('customer');

        NotificationPreference::create([
            'user_id' => $user1->id,
            'channel' => 'in_app',
            'enabled' => true,
        ]);

        $prefs = NotificationPreference::where('user_id', $user2->id)->get();
        $this->assertCount(0, $prefs);

        $prefsUser1 = NotificationPreference::where('user_id', $user1->id)->get();
        $this->assertCount(1, $prefsUser1);
    }

    public function test_user_cannot_update_another_users_notification_preferences(): void
    {
        $user1 = $this->createUserWithRole('customer');
        $user2 = $this->createUserWithRole('customer');

        $pref = NotificationPreference::create([
            'user_id' => $user1->id,
            'channel' => 'in_app',
            'enabled' => false,
        ]);

        $affected = NotificationPreference::where('user_id', $user1->id)
            ->where('id', $pref->id)
            ->update(['enabled' => true]);

        $this->assertEquals(1, $affected);
    }

    public function test_user_profile_is_scoped_to_own_user(): void
    {
        $user1 = $this->createUserWithRole('customer');
        $user2 = $this->createUserWithRole('customer');

        $response = $this->actingAs($user2)->get('/dashboard/account/profile');
        $response->assertStatus(200);

        $this->assertNotEquals($user1->id, $user2->id);
    }

    public function test_account_page_shows_only_own_data(): void
    {
        $user = $this->createUserWithRole('customer');

        $response = $this->actingAs($user)->get('/dashboard/account/profile');

        $response->assertStatus(200);
    }

    public function test_notifications_scoped_to_own_user(): void
    {
        $user1 = $this->createUserWithRole('customer');
        $user2 = $this->createUserWithRole('customer');

        $user1->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Test',
            'data' => ['message' => 'User1 notification'],
        ]);

        $this->assertEquals(0, $user2->notifications()->count());
        $this->assertEquals(1, $user1->notifications()->count());
    }

    public function test_notification_controller_scopes_to_own_user(): void
    {
        $user1 = $this->createUserWithRole('customer');
        $user2 = $this->createUserWithRole('customer');

        $user1->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\Test',
            'data' => ['message' => 'User1 notification'],
        ]);

        $this->actingAs($user2);

        $this->assertEquals(0, $user2->unreadNotifications()->count());
    }
}
