<?php

namespace Tests\Feature\Modules\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Nreljed\Blog\Models\Post;
use Tests\BootsFeatures;
use Tests\TestCase;

class PostGalleryPreviewTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();
    }

    public function test_store_persists_seo_into_metadata(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)->post(route('dashboard.posts.store'), [
            'title' => 'SEO Post',
            'content' => '<p>Hello</p>',
            'status' => 'draft',
            'seo_metadata' => [
                'seo_score' => 88,
            ],
        ])->assertRedirect(route('dashboard.posts.index'));

        $post = Post::where('title', 'SEO Post')->firstOrFail();

        $this->assertSame(88, $post->metadata['seo']['seo_score']);
    }

    public function test_signed_preview_renders_unpublished_post(): void
    {
        $post = Post::factory()->draft()->create(['title' => 'Secret Draft']);

        $url = URL::signedRoute('blog.preview', ['post' => $post->id]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.title', 'Secret Draft')
                ->where('post.status', 'draft')
                ->where('preview', true));
    }

    public function test_preview_rejects_invalid_signature(): void
    {
        $post = Post::factory()->draft()->create();

        $this->get(route('blog.preview', $post).'?signature=invalid')
            ->assertForbidden();
    }

    public function test_published_post_has_no_preview_url_but_draft_does(): void
    {
        $admin = $this->createUserWithRole('admin');
        $draft = Post::factory()->draft()->create([
            'title' => 'Draft Post',
            'created_at' => now()->subMinutes(5),
        ]);
        Post::factory()->published()->create([
            'title' => 'Live Post',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('dashboard.posts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.preview_url', null)
                ->has('posts.1.preview_url'));

        $this->actingAs($admin)->get(route('dashboard.posts.edit', $draft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('preview_url'));
    }
}
