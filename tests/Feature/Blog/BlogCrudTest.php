<?php

namespace Tests\Feature\Blog;

use App\Blog\Models\Post;
use Database\Factories\PostFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class BlogCrudTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_post_index_requires_permission(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->get(route('dashboard.posts.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_list_posts(): void
    {
        $response = $this->actingAs($this->admin())->get(route('dashboard.posts.index'));

        $response->assertStatus(200);
    }

    public function test_admin_can_create_post(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
            'title' => 'Test Post Title',
            'content' => 'Some content here.',
            'status' => 'draft',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post Title',
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_update_post(): void
    {
        $post = PostFactory::new()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.posts.update', $post), [
                'title' => 'Updated Post Title',
                'content' => 'Updated content.',
                'status' => 'published',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Post Title',
            'status' => 'published',
        ]);
    }

    public function test_admin_can_delete_post(): void
    {
        $post = PostFactory::new()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('dashboard.posts.destroy', $post));

        $response->assertRedirect();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_post_requires_title(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
            'content' => 'Content without a title.',
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_post_slug_is_auto_generated(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
            'title' => 'Auto Slug Post',
            'content' => 'Content.',
            'status' => 'draft',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'title' => 'Auto Slug Post',
        ]);

        $post = Post::where('title', 'Auto Slug Post')->first();
        $this->assertNotNull($post->slug);
        $this->assertNotEmpty($post->slug);
    }

    public function test_post_stores_heading_content(): void
    {
        $html = '<h1>Main Title</h1><h2>Section</h2><h3>Subsection</h3><h4>Detail</h4><h5>Minor</h5><h6>Tiny</h6>';

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
            'title' => 'Heading Test',
            'content' => $html,
            'status' => 'published',
        ]);

        $response->assertRedirect();

        $post = Post::where('title', 'Heading Test')->first();
        $this->assertStringContainsString('<h1>Main Title</h1>', $post->content);
        $this->assertStringContainsString('<h2>Section</h2>', $post->content);
        $this->assertStringContainsString('<h3>Subsection</h3>', $post->content);
        $this->assertStringContainsString('<h4>Detail</h4>', $post->content);
        $this->assertStringContainsString('<h5>Minor</h5>', $post->content);
        $this->assertStringContainsString('<h6>Tiny</h6>', $post->content);
    }

    public function test_post_stores_code_block_content(): void
    {
        $html = '<pre><code class="language-typescript">function greet(name: string): string {\n  return `Hello ${name}`;\n}</code></pre>';

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
            'title' => 'Code Block Test',
            'content' => $html,
            'status' => 'published',
        ]);

        $response->assertRedirect();

        $post = Post::where('title', 'Code Block Test')->first();
        $this->assertStringContainsString('<pre>', $post->content);
        $this->assertStringContainsString('language-typescript', $post->content);
        $this->assertStringContainsString('function greet', $post->content);
    }

    public function test_post_stores_table_content(): void
    {
        $html = '<table><thead><tr><th>Feature</th><th>Status</th></tr></thead><tbody><tr><td>Bold</td><td>Complete</td></tr><tr><td>Italic</td><td>Complete</td></tr></tbody></table>';

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
            'title' => 'Table Test',
            'content' => $html,
            'status' => 'published',
        ]);

        $response->assertRedirect();

        $post = Post::where('title', 'Table Test')->first();
        $this->assertStringContainsString('<table>', $post->content);
        $this->assertStringContainsString('<thead>', $post->content);
        $this->assertStringContainsString('Feature', $post->content);
        $this->assertStringContainsString('<td>Bold</td>', $post->content);
    }

    public function test_post_stores_inline_formatting(): void
    {
        $html = '<p>This is <strong>bold</strong>, <em>italic</em>, <u>underlined</u>, <s>strikethrough</s>, and <code>inline code</code> text.</p>';

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
            'title' => 'Formatting Test',
            'content' => $html,
            'status' => 'published',
        ]);

        $response->assertRedirect();

        $post = Post::where('title', 'Formatting Test')->first();
        $this->assertStringContainsString('<strong>bold</strong>', $post->content);
        $this->assertStringContainsString('<em>italic</em>', $post->content);
        $this->assertStringContainsString('<u>underlined</u>', $post->content);
        $this->assertStringContainsString('<s>strikethrough</s>', $post->content);
        $this->assertStringContainsString('<code>inline code</code>', $post->content);
    }

    public function test_post_stores_horizontal_rule(): void
    {
        $html = '<p>Before</p><hr /><p>After</p>';

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
            'title' => 'HR Test',
            'content' => $html,
            'status' => 'published',
        ]);

        $response->assertRedirect();

        $post = Post::where('title', 'HR Test')->first();
        $this->assertStringContainsString('<hr', $post->content);
        $this->assertStringContainsString('Before', $post->content);
        $this->assertStringContainsString('After', $post->content);
    }

    public function test_post_stores_blockquote_content(): void
    {
        $html = '<blockquote><p>Important quote here.</p></blockquote>';

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
            'title' => 'Blockquote Test',
            'content' => $html,
            'status' => 'published',
        ]);

        $response->assertRedirect();

        $post = Post::where('title', 'Blockquote Test')->first();
        $this->assertStringContainsString('<blockquote>', $post->content);
        $this->assertStringContainsString('Important quote here.', $post->content);
    }

    public function test_post_update_preserves_rich_content(): void
    {
        $post = PostFactory::new()->create([
            'content' => '<h1>Original Heading</h1><p>Original content.</p>',
        ]);

        $newContent = '<h1>Updated Heading</h1><p>Updated content with <strong>bold</strong>.</p>';

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.posts.update', $post), [
                'title' => $post->title,
                'content' => $newContent,
                'status' => $post->status,
            ]);

        $response->assertRedirect();

        $post->refresh();
        $this->assertStringContainsString('<h1>Updated Heading</h1>', $post->content);
        $this->assertStringContainsString('<strong>bold</strong>', $post->content);
        $this->assertStringNotContainsString('Original Heading', $post->content);
    }

    public function test_post_stores_empty_content(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
            'title' => 'Empty Content Post',
            'content' => '',
            'status' => 'draft',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'title' => 'Empty Content Post',
        ]);

        $post = Post::where('title', 'Empty Content Post')->first();
        $this->assertNull($post->content);
    }

    public function test_post_stores_null_content(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.store'), [
            'title' => 'Null Content Post',
            'status' => 'draft',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('posts', [
            'title' => 'Null Content Post',
        ]);
    }
}
