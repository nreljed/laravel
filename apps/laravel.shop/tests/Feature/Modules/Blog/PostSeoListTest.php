<?php

namespace Tests\Feature\Modules\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Nreljed\Blog\Models\Post;
use Tests\BootsFeatures;
use Tests\TestCase;

class PostSeoListTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();
    }

    public function test_index_maps_seo_score_and_status_for_each_post(): void
    {
        $admin = $this->createUserWithRole('admin');

        Post::factory()->create([
            'title' => 'Good SEO Post',
            'created_at' => now()->subHours(3),
            'metadata' => ['seo' => ['seo_score' => 92]],
        ]);
        Post::factory()->create([
            'title' => 'Needs Work Post',
            'created_at' => now()->subHours(2),
            'metadata' => ['seo' => ['seo_score' => 60]],
        ]);
        Post::factory()->create([
            'title' => 'Poor SEO Post',
            'created_at' => now()->subHours(1),
            'metadata' => ['seo' => ['seo_score' => 35]],
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.posts.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('posts', 3)
            ->where('posts.0.seo_status', 'poor')
            ->where('posts.0.seo_score', 35)
            ->where('posts.1.seo_status', 'needs')
            ->where('posts.1.seo_score', 60)
            ->where('posts.2.seo_status', 'good')
            ->where('posts.2.seo_score', 92));
    }

    public function test_index_marks_posts_without_seo_metadata_as_none(): void
    {
        $admin = $this->createUserWithRole('admin');

        Post::factory()->create([
            'title' => 'No SEO Post',
            'created_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.posts.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('posts', 1)
            ->where('posts.0.seo_status', 'none')
            ->where('posts.0.seo_score', null));
    }

    public function test_seo_status_classification_matches_thresholds(): void
    {
        $post = new Post;

        $post->setAttribute('metadata', ['seo' => ['seo_score' => 80]]);
        $this->assertSame('good', $post->seoStatus());
        $this->assertSame(80, $post->seoScore());

        $post->setAttribute('metadata', ['seo' => ['seo_score' => 50]]);
        $this->assertSame('needs', $post->seoStatus());

        $post->setAttribute('metadata', ['seo' => ['seo_score' => 49]]);
        $this->assertSame('poor', $post->seoStatus());

        $post->setAttribute('metadata', null);
        $this->assertSame('none', $post->seoStatus());
        $this->assertNull($post->seoScore());
    }
}
