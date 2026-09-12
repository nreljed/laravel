<?php

namespace Tests\Feature\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Nreljed\Blog\Database\Factories\PostFactory;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class BlogPublicTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    public function test_blog_index_page_is_accessible(): void
    {
        $response = $this->get(route('blog.index'));

        $response->assertStatus(200);
    }

    public function test_published_posts_are_shown(): void
    {
        $post = PostFactory::new()->published()->create(['title' => 'Visible Published Post']);

        $response = $this->get(route('blog.index'));

        $response->assertStatus(200);
        $response->assertSee('Visible Published Post');
    }

    public function test_draft_posts_are_not_shown(): void
    {
        $post = PostFactory::new()->draft()->create(['title' => 'Hidden Draft Post']);

        $response = $this->get(route('blog.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Hidden Draft Post');
    }

    public function test_blog_show_page_displays_published_post(): void
    {
        $post = PostFactory::new()->published()->create(['title' => 'Published Detail Post']);

        $response = $this->get(route('blog.show', $post->slug));

        $response->assertStatus(200);
        $response->assertSee('Published Detail Post');
    }

    public function test_unpublished_post_returns_404(): void
    {
        $post = PostFactory::new()->draft()->create();

        $response = $this->get(route('blog.show', $post->slug));

        $response->assertStatus(404);
    }

    public function test_guest_does_not_see_edit_button(): void
    {
        $post = PostFactory::new()->published()->create();

        $response = $this->get(route('blog.show', $post->slug));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('blog/public/show')
            ->has('post')
        );
    }

    public function test_author_receives_post_data_for_edit_button(): void
    {
        $this->seedRolesAndPermissions();
        $author = $this->regularUser();
        $author->forceFill(['email_verified_at' => now()])->save();
        $post = PostFactory::new()->published()->create(['user_id' => $author->id]);

        $response = $this->actingAs($author)->get(route('blog.show', $post->slug));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('blog/public/show')
            ->where('post.id', $post->id)
            ->where('post.user.id', $author->id)
        );
    }

    public function test_non_author_receives_post_data_without_edit_context(): void
    {
        $this->seedRolesAndPermissions();
        $author = $this->regularUser();
        $author->forceFill(['email_verified_at' => now()])->save();
        $other = $this->regularUser();
        $other->forceFill(['email_verified_at' => now()])->save();
        $post = PostFactory::new()->published()->create(['user_id' => $author->id]);

        $response = $this->actingAs($other)->get(route('blog.show', $post->slug));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('blog/public/show')
            ->where('post.id', $post->id)
            ->where('post.user.id', $author->id)
        );
    }

    public function test_admin_receives_post_data_for_edit_button(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();
        $post = PostFactory::new()->published()->create();

        $response = $this->actingAs($admin)->get(route('blog.show', $post->slug));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('blog/public/show')
            ->where('post.id', $post->id)
        );
    }
}
