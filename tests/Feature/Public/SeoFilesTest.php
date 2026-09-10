<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Posts\Models\Post;
use Nreljed\DashboardCore\Posts\Models\PostCategory;
use Nreljed\DashboardCore\Posts\Models\PostTag;
use Nreljed\DashboardCore\Services\SettingsService;
use Tests\TestCase;

class SeoFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SettingsService::set('app_url', 'https://example.test');
    }

    public function test_sitemap_returns_xml_with_static_pages(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>https://example.test/</loc>', false)
            ->assertSee('<loc>https://example.test/about</loc>', false)
            ->assertSee('<loc>https://example.test/blog</loc>', false);
    }

    public function test_sitemap_includes_published_posts_only(): void
    {
        $user = User::factory()->create();

        $published = Post::factory()->published()->create([
            'user_id' => $user->id,
            'slug' => 'published-post',
        ]);
        $draft = Post::factory()->draft()->create([
            'user_id' => $user->id,
            'slug' => 'draft-post',
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertSee('<loc>https://example.test/blog/published-post</loc>', false)
            ->assertDontSee('https://example.test/blog/draft-post');
    }

    public function test_sitemap_includes_categories_and_tags_with_published_posts(): void
    {
        $user = User::factory()->create();
        $category = PostCategory::factory()->create(['slug' => 'guides']);
        $tag = PostTag::factory()->create(['slug' => 'tutorial']);

        $post = Post::factory()->published()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
        $post->tags()->attach($tag);

        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertSee('<loc>https://example.test/blog?category=guides</loc>', false)
            ->assertSee('<loc>https://example.test/blog?tag=tutorial</loc>', false);
    }

    public function test_robots_txt_mentions_sitemap_and_disallows_dashboard(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: https://example.test/sitemap.xml', false)
            ->assertSee('Disallow: /dashboard', false);
    }
}
