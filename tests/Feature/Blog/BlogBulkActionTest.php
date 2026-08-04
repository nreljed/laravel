<?php

namespace Tests\Feature\Blog;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Nreljed\DashboardCore\Database\Factories\PostFactory;
use Nreljed\DashboardCore\Models\Permission;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Models\User;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class BlogBulkActionTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_bulk_action_requires_authentication(): void
    {
        $post = PostFactory::new()->draft()->create();

        $response = $this->postJson(route('dashboard.posts.bulk.action'), [
            'ids' => [$post->id],
            'action' => 'publish',
        ]);

        $response->assertStatus(401);
    }

    public function test_bulk_action_requires_valid_ids(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.bulk.action'), [
            'ids' => [9999],
            'action' => 'publish',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ids.0');
    }

    public function test_bulk_action_requires_valid_action(): void
    {
        $post = PostFactory::new()->create();

        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.bulk.action'), [
            'ids' => [$post->id],
            'action' => 'invalid_action',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('action');
    }

    public function test_bulk_action_requires_ids_array(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.bulk.action'), [
            'ids' => 'not_an_array',
            'action' => 'publish',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ids');
    }

    public function test_bulk_publish_sets_status_and_published_at(): void
    {
        $posts = PostFactory::new()->draft()->count(3)->create();

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.bulk.action'), [
            'ids' => $posts->pluck('id')->toArray(),
            'action' => 'publish',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', '3 post(s) updated to published.');

        foreach ($posts as $post) {
            $this->assertDatabaseHas('posts', [
                'id' => $post->id,
                'status' => 'published',
            ]);
            $this->assertNotNull($post->fresh()->published_at);
        }
    }

    public function test_bulk_draft_sets_status_and_clears_published_at(): void
    {
        $posts = PostFactory::new()->published()->count(3)->create();

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.bulk.action'), [
            'ids' => $posts->pluck('id')->toArray(),
            'action' => 'draft',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', '3 post(s) updated to draft.');

        foreach ($posts as $post) {
            $this->assertDatabaseHas('posts', [
                'id' => $post->id,
                'status' => 'draft',
            ]);
            $this->assertNull($post->fresh()->published_at);
        }
    }

    public function test_bulk_delete_removes_posts(): void
    {
        $posts = PostFactory::new()->count(3)->create();
        $postIds = $posts->pluck('id')->toArray();

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.bulk.action'), [
            'ids' => $postIds,
            'action' => 'delete',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', '3 post(s) deleted successfully.');

        foreach ($postIds as $id) {
            $this->assertDatabaseMissing('posts', ['id' => $id]);
        }
    }

    public function test_bulk_delete_removes_featured_image_files(): void
    {
        Storage::fake('public');

        $posts = PostFactory::new()->count(2)->create([
            'featured_image' => '/storage/posts/featured.webp',
        ]);
        $postIds = $posts->pluck('id')->toArray();
        Storage::disk('public')->put('posts/featured.webp', 'image');

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.bulk.action'), [
            'ids' => $postIds,
            'action' => 'delete',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', '2 post(s) deleted successfully.');

        Storage::disk('public')->assertMissing('posts/featured.webp');
    }

    public function test_bulk_delete_with_mixed_ownership_only_deletes_authorized(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'blog_editor'],
            ['name' => 'Blog Editor', 'description' => 'Can delete posts']
        );
        $role->permissions()->sync(Permission::whereIn('slug', ['posts.delete'])->pluck('id'));

        $user = $this->createUserWithRole('blog_editor');
        $otherUser = $this->regularUser();

        $userPost = PostFactory::new()->create(['user_id' => $user->id]);
        $otherPost = PostFactory::new()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post(route('dashboard.posts.bulk.action'), [
            'ids' => [$userPost->id, $otherPost->id],
            'action' => 'delete',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('posts', ['id' => $userPost->id]);
        $this->assertDatabaseHas('posts', ['id' => $otherPost->id]);
    }

    public function test_regular_user_without_posts_delete_cannot_bulk_action(): void
    {
        $user = User::factory()->create();
        $post = PostFactory::new()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('dashboard.posts.bulk.action'), [
            'ids' => [$post->id],
            'action' => 'publish',
        ]);

        $response->assertStatus(403);
    }

    public function test_bulk_action_with_empty_ids_returns_error(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.bulk.action'), [
            'ids' => [],
            'action' => 'publish',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ids');
    }

    public function test_editor_can_bulk_publish_posts_authored_by_others(): void
    {
        $editor = $this->editor();
        $other = User::factory()->create();

        $posts = PostFactory::new()->draft()->count(2)->create(['user_id' => $other->id]);

        $response = $this->actingAs($editor)->post(route('dashboard.posts.bulk.action'), [
            'ids' => $posts->pluck('id')->toArray(),
            'action' => 'publish',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', '2 post(s) updated to published.');

        foreach ($posts as $post) {
            $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'published']);
        }
    }

    public function test_author_can_bulk_publish_own_posts(): void
    {
        $author = $this->contributor();
        $post = PostFactory::new()->draft()->create(['user_id' => $author->id]);

        $response = $this->actingAs($author)->post(route('dashboard.posts.bulk.action'), [
            'ids' => [$post->id],
            'action' => 'publish',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', '1 post(s) updated to published.');
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'published']);
    }

    public function test_author_cannot_bulk_publish_posts_authored_by_others(): void
    {
        $author = $this->contributor();
        $other = User::factory()->create();

        $post = PostFactory::new()->draft()->create(['user_id' => $other->id]);

        $response = $this->actingAs($author)->post(route('dashboard.posts.bulk.action'), [
            'ids' => [$post->id],
            'action' => 'publish',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'draft']);
    }

    public function test_user_can_bulk_delete_own_post(): void
    {
        $user = $this->regularUser();
        $post = PostFactory::new()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('dashboard.posts.bulk.action'), [
            'ids' => [$post->id],
            'action' => 'delete',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', '1 post(s) deleted successfully.');
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }
}
