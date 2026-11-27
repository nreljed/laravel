<?php

namespace Tests\Feature\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Blog\Ai\Agents\ImageAltTextAgent;
use Nreljed\Blog\Database\Factories\PostFactory;
use Nreljed\Platform\Ai\Agents\SeoAgent;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

/**
 * Local, deterministic coverage for the SEO endpoints.
 *
 * The AI agents are mocked so these tests never depend on a BYOK provider's
 * quota or network availability. Provider-side failures are therefore not a
 * reason to defer verification to production.
 */
class SeoLocalFlowTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_seo_generate_uses_ai_result_and_caches_it(): void
    {
        $admin = $this->admin();

        $this->mock(SeoAgent::class, function ($mock) {
            $mock->shouldReceive('generateSeo')
                ->once()
                ->andReturn($this->seoPayload());
        });

        $payload = [
            'title' => 'Local SEO test',
            'content' => '<h2>Testing locally</h2><p>Laravel SEO testing without a live provider.</p>',
            'keyword' => 'laravel seo',
        ];

        $this->actingAs($admin)->postJson(route('dashboard.posts.seo.generate'), $payload)
            ->assertOk()
            ->assertJsonPath('seo.primary_keyword', 'laravel seo')
            ->assertJsonPath('seo.seo_score', 88);

        // Identical request: served from the 24h cache, so the agent is not called again.
        $this->actingAs($admin)->postJson(route('dashboard.posts.seo.generate'), $payload)
            ->assertOk()
            ->assertJsonPath('seo.primary_keyword', 'laravel seo');
    }

    public function test_force_regenerate_bypasses_the_cache(): void
    {
        $admin = $this->admin();

        $this->mock(SeoAgent::class, function ($mock) {
            $mock->shouldReceive('generateSeo')
                ->twice()
                ->andReturn($this->seoPayload());
        });

        $payload = [
            'title' => 'Force regeneration',
            'content' => '<p>Testing the regenerate control.</p>',
        ];

        $this->actingAs($admin)->postJson(route('dashboard.posts.seo.generate'), $payload)->assertOk();
        $this->actingAs($admin)->postJson(route('dashboard.posts.seo.generate'), $payload + ['force' => true])->assertOk();
    }

    public function test_related_posts_are_suggested_from_published_content(): void
    {
        $admin = $this->admin();

        $current = PostFactory::new()->create(['user_id' => $admin->id]);
        $match = PostFactory::new()->published()->create([
            'user_id' => $admin->id,
            'title' => 'Laravel SEO guide',
            'focus_keyword' => 'laravel seo',
        ]);
        PostFactory::new()->published()->create([
            'user_id' => $admin->id,
            'title' => 'Unrelated cooking recipe',
        ]);
        PostFactory::new()->create([
            'user_id' => $admin->id,
            'title' => 'Laravel SEO draft',
            'focus_keyword' => 'laravel seo',
        ]);

        $response = $this->actingAs($admin)->getJson(route('dashboard.posts.seo.related', [
            'keyword' => 'laravel seo',
            'title' => 'Laravel SEO tips',
            'exclude_id' => $current->id,
        ]));

        $response->assertOk();
        $ids = collect($response->json('posts'))->pluck('id')->all();

        $this->assertContains($match->id, $ids);
        $this->assertNotContains($current->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_image_alt_text_endpoint_returns_generated_text(): void
    {
        $this->mock(ImageAltTextAgent::class, function ($mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn('Laptop displaying Laravel documentation');
        });

        $this->actingAs($this->admin())
            ->postJson(route('dashboard.posts.seo.image-alt'), [
                'source' => '/storage/posts/laravel-docs.jpg',
                'context' => 'An article about local SEO testing.',
            ])
            ->assertOk()
            ->assertJsonPath('alt_text', 'Laptop displaying Laravel documentation');
    }

    public function test_post_update_persists_og_title_and_description(): void
    {
        $post = PostFactory::new()->create();

        $this->actingAs($this->admin())
            ->put(route('dashboard.posts.update', $post), [
                'title' => $post->title,
                'status' => $post->status,
                'og_title' => 'Social title',
                'og_description' => 'Social description for link previews.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'og_title' => 'Social title',
            'og_description' => 'Social description for link previews.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function seoPayload(): array
    {
        return [
            'title_options' => ['Local SEO title', 'Alternative title'],
            'meta_description_options' => ['A locally generated description.'],
            'primary_keyword' => 'laravel seo',
            'secondary_keywords' => ['local testing'],
            'seo_score' => 88,
            'recommendations' => [],
            'suggested_category' => 'Development',
            'suggested_tags' => ['Laravel'],
        ];
    }
}
