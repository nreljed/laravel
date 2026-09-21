<?php

namespace Tests\Feature\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Blog\Database\Factories\PostFactory;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Models\Permission;
use Nreljed\Platform\Models\User;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class BlogPermissionsTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_unauthenticated_user_cannot_access_posts(): void
    {
        $response = $this->get(route('dashboard.posts.index'));

        $response->assertRedirect();
    }

    public function test_user_without_posts_view_gets_403(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard.posts.index'));

        $response->assertStatus(403);
    }

    public function test_user_with_posts_view_can_see_index(): void
    {
        $user = User::factory()->create();
        $permission = Permission::where('slug', 'posts.view')->first();
        $user->permissions()->attach($permission);

        $response = $this->actingAs($user)->get(route('dashboard.posts.index'));

        $response->assertStatus(200);
    }

    public function test_user_without_posts_create_cannot_store(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('dashboard.posts.store'), [
            'title' => 'Unauthorized Post',
        ]);

        $response->assertStatus(403);
    }

    public function test_non_owner_cannot_edit_post(): void
    {
        $owner = User::factory()->create();
        $permission = Permission::where('slug', 'posts.create')->first();
        $owner->permissions()->attach($permission);

        $post = PostFactory::new()->create(['user_id' => $owner->id]);

        $nonOwner = $this->regularUser();

        $response = $this->actingAs($nonOwner)->get(route('dashboard.posts.edit', $post));

        $response->assertStatus(403);
    }

    public function test_admin_can_edit_any_post(): void
    {
        $owner = User::factory()->create();
        $permission = Permission::where('slug', 'posts.create')->first();
        $owner->permissions()->attach($permission);

        $post = PostFactory::new()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($this->admin())->get(route('dashboard.posts.edit', $post));

        $response->assertStatus(200);
    }

    public function test_editor_can_edit_post_authored_by_another_user(): void
    {
        $owner = User::factory()->create();
        $post = PostFactory::new()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($this->editor())->get(route('dashboard.posts.edit', $post));

        $response->assertStatus(200);
    }

    public function test_editor_can_delete_post_authored_by_another_user(): void
    {
        $owner = User::factory()->create();
        $post = PostFactory::new()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($this->editor())->delete(route('dashboard.posts.destroy', $post));

        $response->assertRedirect();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_editor_index_shows_all_posts(): void
    {
        $editor = $this->editor();
        $other = User::factory()->create();

        PostFactory::new()->count(2)->create(['user_id' => $other->id]);
        PostFactory::new()->create(['user_id' => $editor->id]);

        $response = $this->actingAs($editor)->get(route('dashboard.posts.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('posts/index', false)
            ->has('posts', 3));
    }

    public function test_author_can_edit_own_post(): void
    {
        $author = $this->contributor();
        $post = PostFactory::new()->create(['user_id' => $author->id]);

        $response = $this->actingAs($author)->get(route('dashboard.posts.edit', $post));

        $response->assertStatus(200);
    }

    public function test_author_cannot_edit_post_authored_by_another_user(): void
    {
        $owner = User::factory()->create();
        $post = PostFactory::new()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($this->contributor())->get(route('dashboard.posts.edit', $post));

        $response->assertStatus(403);
    }

    public function test_author_can_delete_own_post(): void
    {
        $author = $this->contributor();
        $post = PostFactory::new()->create(['user_id' => $author->id]);

        $response = $this->actingAs($author)->delete(route('dashboard.posts.destroy', $post));

        $response->assertRedirect();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_author_index_shows_only_own_posts(): void
    {
        $author = $this->contributor();
        $other = User::factory()->create();

        $ownPost = PostFactory::new()->create(['user_id' => $author->id]);
        PostFactory::new()->create(['user_id' => $other->id]);

        $response = $this->actingAs($author)->get(route('dashboard.posts.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('posts/index', false)
            ->has('posts', 1)
            ->where('posts.0.id', $ownPost->id));
    }

    public function test_user_role_can_edit_own_post(): void
    {
        $user = $this->regularUser();
        $post = PostFactory::new()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('dashboard.posts.edit', $post));

        $response->assertStatus(200);
    }

    public function test_user_role_can_delete_own_post(): void
    {
        $user = $this->regularUser();
        $post = PostFactory::new()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('dashboard.posts.destroy', $post));

        $response->assertRedirect();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_user_role_cannot_edit_post_authored_by_another_user(): void
    {
        $owner = User::factory()->create();
        $post = PostFactory::new()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($this->regularUser())->get(route('dashboard.posts.edit', $post));

        $response->assertStatus(403);
    }

    public function test_user_role_index_shows_only_own_posts(): void
    {
        $user = $this->regularUser();
        $other = User::factory()->create();

        $ownPost = PostFactory::new()->create(['user_id' => $user->id]);
        PostFactory::new()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('dashboard.posts.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('posts/index', false)
            ->has('posts', 1)
            ->where('posts.0.id', $ownPost->id));
    }
}
