<?php

namespace Tests\Feature\TestPosts;

use App\TestPosts\Models\TestPost;
use Database\Factories\TestPostFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class TestPostCrudTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_test_post_index_requires_permission(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->get(route('dashboard.test-posts.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_list_test_posts(): void
    {
        $response = $this->actingAs($this->admin())->get(route('dashboard.test-posts.index'));

        $response->assertStatus(200);
    }

    public function test_admin_can_create_test_post(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.test-posts.store'), [
            'title' => 'Test Post Title',
            'content' => 'Some content here.',
            'status' => 'draft',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('test_posts', [
            'title' => 'Test Post Title',
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_update_test_post(): void
    {
        $testPost = TestPostFactory::new()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.test-posts.update', $testPost), [
                'title' => 'Updated Test Post Title',
                'content' => 'Updated content.',
                'status' => 'published',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('test_posts', [
            'id' => $testPost->id,
            'title' => 'Updated Test Post Title',
            'status' => 'published',
        ]);
    }

    public function test_admin_can_delete_test_post(): void
    {
        $testPost = TestPostFactory::new()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('dashboard.test-posts.destroy', $testPost));

        $response->assertRedirect();
        $this->assertDatabaseMissing('test_posts', ['id' => $testPost->id]);
    }

    public function test_delete_removes_featured_and_gallery_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('test-posts/featured.webp', 'image');
        Storage::disk('public')->put('test-posts/gallery-1.webp', 'image');
        Storage::disk('public')->put('test-posts/gallery-2.webp', 'image');

        $testPost = TestPostFactory::new()->create([
            'featured_image' => '/storage/test-posts/featured.webp',
            'gallery_images' => [
                ['url' => '/storage/test-posts/gallery-1.webp'],
                ['url' => '/storage/test-posts/gallery-2.webp'],
            ],
        ]);

        $this->actingAs($this->admin())
            ->delete(route('dashboard.test-posts.destroy', $testPost))
            ->assertRedirect();

        $this->assertDatabaseMissing('test_posts', ['id' => $testPost->id]);
        Storage::disk('public')->assertMissing('test-posts/featured.webp');
        Storage::disk('public')->assertMissing('test-posts/gallery-1.webp');
        Storage::disk('public')->assertMissing('test-posts/gallery-2.webp');
    }

    public function test_test_post_requires_title(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.test-posts.store'), [
            'content' => 'Content without a title.',
        ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_test_post_slug_is_auto_generated(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.test-posts.store'), [
            'title' => 'Auto Slug Test Post',
            'content' => 'Content.',
            'status' => 'draft',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('test_posts', [
            'title' => 'Auto Slug Test Post',
        ]);

        $testPost = TestPost::where('title', 'Auto Slug Test Post')->first();
        $this->assertNotNull($testPost->slug);
        $this->assertNotEmpty($testPost->slug);
    }
}
