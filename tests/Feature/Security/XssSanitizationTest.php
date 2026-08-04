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

    public function test_sanitizer_preserves_lexical_column_layout_attributes(): void
    {
        $html = '<div style="grid-template-columns: 1fr 1fr;" data-lexical-layout-container="true" data-responsive-mode="full">'
            .'<div data-lexical-layout-item="true"><p>Left</p></div>'
            .'<div data-lexical-layout-item="true"><p>Right</p></div>'
            .'</div>';

        $sanitized = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('data-lexical-layout-container="true"', $sanitized);
        $this->assertStringContainsString('data-lexical-layout-item="true"', $sanitized);
        $this->assertStringContainsString('data-responsive-mode="full"', $sanitized);
        $this->assertStringContainsString('grid-template-columns: 1fr 1fr', $sanitized);
        $this->assertStringContainsString('Left', $sanitized);
        $this->assertStringContainsString('Right', $sanitized);
    }

    public function test_sanitizer_preserves_lexical_checklist_markup(): void
    {
        $html = '<ul data-is-checklist="1"><li role="checkbox" tabindex="-1" aria-checked="true" value="1" class="lexical-checklist-checked">Done</li>'
            .'<li role="checkbox" tabindex="-1" aria-checked="false" value="2" class="lexical-checklist-unchecked">Todo</li></ul>';

        $sanitized = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('data-is-checklist="1"', $sanitized);
        $this->assertStringContainsString('aria-checked="true"', $sanitized);
        $this->assertStringContainsString('aria-checked="false"', $sanitized);
        $this->assertStringContainsString('lexical-checklist-checked', $sanitized);
        $this->assertStringContainsString('lexical-checklist-unchecked', $sanitized);
        $this->assertStringContainsString('Done', $sanitized);
        $this->assertStringContainsString('Todo', $sanitized);
    }

    public function test_sanitizer_preserves_lexical_internal_checklist_type(): void
    {
        $html = '<ul __lexicalListType="check"><li role="checkbox" tabindex="-1" aria-checked="true">Done</li></ul>';

        $sanitized = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('__lexicallisttype="check"', $sanitized);
        $this->assertStringContainsString('aria-checked="true"', $sanitized);
    }

    public function test_sanitizer_strips_checklist_event_handlers(): void
    {
        $html = '<ul data-is-checklist="1"><li role="checkbox" tabindex="-1" aria-checked="true" onmouseover="alert(1)">Done</li></ul>';

        $sanitized = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('data-is-checklist="1"', $sanitized);
        $this->assertStringContainsString('aria-checked="true"', $sanitized);
        $this->assertStringNotContainsString('onmouseover', $sanitized);
        $this->assertStringContainsString('Done', $sanitized);
    }

    public function test_sanitizer_still_strips_layout_script_content(): void
    {
        $html = '<div data-lexical-layout-container="true">'
            .'<div data-lexical-layout-item="true"><p>Safe</p><script>alert(1)</script></div>'
            .'</div>';

        $sanitized = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('data-lexical-layout-container="true"', $sanitized);
        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringContainsString('Safe', $sanitized);
    }

    public function test_sanitizer_preserves_allowlisted_youtube_embeds(): void
    {
        $html = '<p>A</p>'
            .'<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="YouTube video" frameborder="0" allowfullscreen="true"></iframe>'
            .'<p>B</p>';

        $sanitized = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('https://www.youtube.com/embed/dQw4w9WgXcQ', $sanitized);
        $this->assertStringContainsString('<iframe', $sanitized);
    }

    public function test_sanitizer_strips_non_allowlisted_iframes(): void
    {
        $html = '<p>A</p><iframe src="https://evil.example/x">bad</iframe><p>B</p>';

        $sanitized = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('iframe', $sanitized);
        $this->assertStringNotContainsString('evil.example', $sanitized);
        $this->assertStringNotContainsString('bad', $sanitized);
    }

    public function test_plain_text_content_is_preserved(): void
    {
        $this->assertSame('Hello world', HtmlSanitizer::sanitize('Hello world'));
        $this->assertNull(HtmlSanitizer::sanitize(null));
    }
}
