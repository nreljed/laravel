<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Blog\Database\Factories\PostFactory;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Models\NotificationPreference;
use Nreljed\Platform\Models\Permission;
use Nreljed\Platform\Models\Role;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class DataIsolationTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_notification_preferences_update_is_scoped_to_own_user(): void
    {
        $user1 = $this->createUserWithRole('user');
        $user2 = $this->createUserWithRole('user');

        $this->actingAs($user1)->post(route('dashboard.account.notifications'), [
            'preferences' => [
                ['type' => 'blog', 'channel' => 'in_app', 'enabled' => true],
            ],
        ])->assertRedirect();

        $this->actingAs($user2)->post(route('dashboard.account.notifications'), [
            'preferences' => [
                ['type' => 'blog', 'channel' => 'in_app', 'enabled' => false],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user1->id,
            'type' => 'blog',
            'channel' => 'in_app',
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user2->id,
            'type' => 'blog',
            'channel' => 'in_app',
            'enabled' => false,
        ]);
    }

    public function test_account_notifications_page_does_not_leak_other_users_preferences(): void
    {
        $user1 = $this->createUserWithRole('user');
        $user2 = $this->createUserWithRole('user');

        NotificationPreference::create([
            'user_id' => $user1->id,
            'type' => 'blog',
            'channel' => 'in_app',
            'enabled' => true,
        ]);

        $response = $this->actingAs($user2)->get(route('dashboard.account.page.notifications'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('preferences', []));
    }

    public function test_non_owner_cannot_update_another_users_post(): void
    {
        $role = $this->createPostEditorRole();
        $editor = $this->createUserWithRole($role->slug);
        $otherUser = $this->regularUser();

        $ownPost = PostFactory::new()->create(['user_id' => $editor->id]);
        $otherPost = PostFactory::new()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($editor)->put(route('dashboard.posts.update', $otherPost), [
            'title' => 'Hijacked Title',
            'content' => 'Hijacked content',
            'status' => 'published',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('posts', [
            'id' => $otherPost->id,
            'title' => $otherPost->title,
        ]);

        $response = $this->actingAs($editor)->put(route('dashboard.posts.update', $ownPost), [
            'title' => 'Own Title',
            'content' => 'Own content',
            'status' => 'published',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'id' => $ownPost->id,
            'title' => 'Own Title',
        ]);
    }

    public function test_non_owner_cannot_delete_another_users_post(): void
    {
        $role = $this->createPostEditorRole();
        $editor = $this->createUserWithRole($role->slug);
        $otherUser = $this->regularUser();

        $ownPost = PostFactory::new()->create(['user_id' => $editor->id]);
        $otherPost = PostFactory::new()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($editor)->delete(route('dashboard.posts.destroy', $otherPost));

        $response->assertStatus(403);
        $this->assertDatabaseHas('posts', ['id' => $otherPost->id]);

        $response = $this->actingAs($editor)->delete(route('dashboard.posts.destroy', $ownPost));

        $response->assertRedirect();
        $this->assertDatabaseMissing('posts', ['id' => $ownPost->id]);
    }

    private function createPostEditorRole(): Role
    {
        $role = Role::firstOrCreate(
            ['slug' => 'post_editor'],
            ['name' => 'Post Editor', 'description' => 'Can manage own posts']
        );
        $role->permissions()->sync(
            Permission::whereIn('slug', ['posts.view', 'posts.create', 'posts.edit', 'posts.delete'])->pluck('id')
        );

        return $role;
    }
}
