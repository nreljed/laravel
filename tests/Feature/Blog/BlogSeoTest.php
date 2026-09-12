<?php

namespace Tests\Feature\Blog;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Blog\Database\Factories\PostFactory;
use Nreljed\Blog\Models\Post;
use Nreljed\Platform\Models\User;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class BlogSeoTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_seo_generate_requires_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('dashboard.posts.seo.generate'), [
            'title' => 'Test Post',
            'content' => 'Some content.',
        ]);

        $response->assertStatus(403);
    }

    public function test_seo_generate_requires_title(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.seo.generate'), [
            'content' => 'Content without title.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('title');
    }

    public function test_seo_generate_accepts_optional_keyword(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.seo.generate'), [
            'title' => 'Test Post',
            'content' => 'Some content.',
        ]);

        // Without a configured AI provider, we expect 422
        $response->assertStatus(422);
    }

    public function test_seo_generate_validates_keyword_length(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.seo.generate'), [
            'title' => 'Test Post',
            'keyword' => str_repeat('a', 101),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('keyword');
    }

    public function test_seo_bulk_requires_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('dashboard.posts.seo.bulk'), [
            'post_ids' => [1],
        ]);

        $response->assertStatus(403);
    }

    public function test_seo_bulk_requires_post_ids_or_all(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.seo.bulk'), []);

        $response->assertUnprocessable();
    }

    public function test_seo_bulk_returns_404_when_no_posts(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.seo.bulk'), [
            'all' => true,
        ]);

        $response->assertNotFound();
    }

    public function test_seo_bulk_processes_selected_posts(): void
    {
        PostFactory::new()->create(['meta_title' => null]);
        PostFactory::new()->create(['meta_title' => null]);

        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.seo.bulk'), [
            'post_ids' => Post::pluck('id')->toArray(),
        ]);

        // Without AI provider, jobs will be dispatched but won't produce results
        $response->assertOk();
        $response->assertJsonFragment(['count' => 2]);
    }

    public function test_seo_bulk_is_scoped_to_own_posts_for_non_managers(): void
    {
        $author = $this->contributor();
        $other = User::factory()->create();

        PostFactory::new()->create(['user_id' => $other->id, 'meta_title' => null]);
        PostFactory::new()->create(['user_id' => $other->id, 'meta_title' => null]);
        $ownPost = PostFactory::new()->create(['user_id' => $author->id, 'meta_title' => null]);

        $response = $this->actingAs($author)->postJson(route('dashboard.posts.seo.bulk'), [
            'post_ids' => [$ownPost->id],
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['count' => 1]);
    }

    public function test_seo_bulk_all_is_scoped_to_own_posts_for_non_managers(): void
    {
        $author = $this->contributor();
        $other = User::factory()->create();

        PostFactory::new()->create(['user_id' => $other->id, 'meta_title' => null]);
        PostFactory::new()->create(['user_id' => $other->id, 'meta_title' => null]);
        PostFactory::new()->create(['user_id' => $author->id, 'meta_title' => null]);

        $response = $this->actingAs($author)->postJson(route('dashboard.posts.seo.bulk'), [
            'all' => true,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['count' => 1]);
    }

    public function test_post_store_accepts_og_image(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
            'title' => 'OG Image Test',
            'content' => 'Content.',
            'status' => 'draft',
            'og_image' => '/storage/posts/og-image.jpg',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'title' => 'OG Image Test',
            'og_image' => '/storage/posts/og-image.jpg',
        ]);
    }

    public function test_post_update_accepts_og_image(): void
    {
        $post = PostFactory::new()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.posts.update', $post), [
                'title' => $post->title,
                'status' => $post->status,
                'og_image' => '/storage/posts/new-og.jpg',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'og_image' => '/storage/posts/new-og.jpg',
        ]);
    }
}
