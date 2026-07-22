<?php

namespace Tests\Feature\Blog;

use Database\Factories\PostFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogPublicTest extends TestCase
{
    use RefreshDatabase;

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
}
