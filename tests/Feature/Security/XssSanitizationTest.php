<?php

namespace Tests\Feature\Security;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Services\HtmlSanitizer;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class XssSanitizationTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_post_store_strips_script_tags_from_content(): void
    {
        $payload = [
            'title' => 'XSS Attempt',
            'content' => '<p>Hello</p><script>alert(1)</script><p>World</p>',
            'status' => 'draft',
        ];

        $this->actingAs($this->admin())->post(route('dashboard.posts.store'), $payload)
            ->assertRedirect();

        $post = Post::latest('id')->first();
        $this->assertStringNotContainsString('<script', $post->content);
        $this->assertStringContainsString('Hello', $post->content);
        $this->assertStringContainsString('World', $post->content);
    }

    public function test_post_store_strips_event_handler_attributes(): void
    {
        $payload = [
            'title' => 'Handler Attempt',
            'content' => '<img src="x" onerror="alert(1)"><a href="https://example.com" onclick="alert(1)">link</a>',
            'status' => 'draft',
        ];

        $this->actingAs($this->admin())->post(route('dashboard.posts.store'), $payload)
            ->assertRedirect();

        $post = Post::latest('id')->first();
        $this->assertStringNotContainsString('onerror', $post->content);
        $this->assertStringNotContainsString('onclick', $post->content);
        $this->assertStringContainsString('link', $post->content);
    }

    public function test_post_store_strips_javascript_urls(): void
    {
        $payload = [
            'title' => 'URL Attempt',
            'content' => '<a href="javascript:alert(1)">evil</a>',
            'status' => 'draft',
        ];

        $this->actingAs($this->admin())->post(route('dashboard.posts.store'), $payload)
            ->assertRedirect();

        $post = Post::latest('id')->first();
        $this->assertStringNotContainsString('javascript:', $post->content);
        $this->assertStringNotContainsString('href=', $post->content);
        $this->assertStringContainsString('evil', $post->content);
    }

    public function test_post_store_unwraps_embedded_media(): void
    {
        $payload = [
            'title' => 'Frame Attempt',
            'content' => '<p>A</p><iframe src="https://evil.example">x</iframe><p>B</p>',
            'status' => 'draft',
        ];

        $this->actingAs($this->admin())->post(route('dashboard.posts.store'), $payload)
            ->assertRedirect();

        $post = Post::latest('id')->first();
        $this->assertStringNotContainsString('iframe', $post->content);
        $this->assertStringNotContainsString('src=', $post->content);
    }

    public function test_post_update_sanitizes_rich_text_content_blocks(): void
    {
        $post = Post::create([
            'user_id' => $this->admin()->id,
            'title' => 'Block Post',
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin())
            ->put(route('dashboard.posts.update', $post), [
                'title' => 'Block Post',
                'content' => '<p>Safe</p><script>alert(2)</script>',
                'status' => 'draft',
                'content_blocks' => [
                    [
                        'type' => 'rich_text',
                        'content' => '<p>Body</p><script>alert(3)</script><img src="x" onerror="alert(4)">',
                        'order' => 0,
                    ],
                    [
                        'type' => 'embed',
                        'content' => 'javascript:alert(5)',
                        'order' => 1,
                    ],
                    [
                        'type' => 'image',
                        'media_url' => 'javascript:alert(6)',
                        'order' => 2,
                    ],
                ],
            ])
            ->assertRedirect();

        $post->refresh();
        $blocks = $post->contentBlocks()->orderBy('order')->get();

        $this->assertStringNotContainsString('<script', $post->content);

        $this->assertSame('rich_text', $blocks[0]->type);
        $this->assertStringContainsString('Body', $blocks[0]->content);
        $this->assertStringNotContainsString('<script', $blocks[0]->content);
        $this->assertStringNotContainsString('onerror', $blocks[0]->content);

        $this->assertNull($blocks[1]->content);

        $this->assertNull($blocks[2]->media_url);
    }

    public function test_excerpt_is_stripped_of_html(): void
    {
        $payload = [
            'title' => 'Excerpt Test',
            'content' => '<p>Body</p>',
            'excerpt' => '<b>Bold</b><script>alert(1)</script>summary',
            'status' => 'draft',
        ];

        $this->actingAs($this->admin())->post(route('dashboard.posts.store'), $payload)
            ->assertRedirect();

        $post = Post::latest('id')->first();
        $this->assertSame('Boldsummary', $post->excerpt);
    }

    public function test_sanitizer_is_idempotent(): void
    {
        $html = '<p>Hello</p><a href="https://example.com">link</a><strong>bold</strong>';

        $once = HtmlSanitizer::sanitize($html);
        $twice = HtmlSanitizer::sanitize($once);

        $this->assertSame($once, $twice);
    }

    public function test_plain_text_content_is_preserved(): void
    {
        $this->assertSame('Hello world', HtmlSanitizer::sanitize('Hello world'));
        $this->assertNull(HtmlSanitizer::sanitize(null));
    }
}
