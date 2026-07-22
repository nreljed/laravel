<?php

namespace Tests\Feature\TestPosts;

use App\TestPosts\Models\TestPost;
use Database\Factories\TestPostFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_test_post_stores_gallery_images(): void
    {
        $galleryImages = [
            ['url' => '/storage/test-posts/image1.webp', 'alt' => 'Image 1', 'caption' => 'Caption 1', 'order' => 0],
            ['url' => '/storage/test-posts/image2.webp', 'alt' => 'Image 2', 'caption' => 'Caption 2', 'order' => 1],
        ];

        $response = $this->actingAs($this->admin())->post(route('dashboard.test-posts.store'), [
            'title' => 'Gallery Test Post',
            'content' => 'Content with gallery.',
            'gallery_images' => $galleryImages,
            'status' => 'published',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('test_posts', [
            'title' => 'Gallery Test Post',
        ]);

        $testPost = TestPost::where('title', 'Gallery Test Post')->first();
        $this->assertNotNull($testPost->gallery_images);
        $this->assertCount(2, $testPost->gallery_images);
        $this->assertEquals('/storage/test-posts/image1.webp', $testPost->gallery_images[0]['url']);
    }

    public function test_test_post_update_with_gallery_images(): void
    {
        $testPost = TestPostFactory::new()->create([
            'gallery_images' => [
                ['url' => '/storage/test-posts/old.webp', 'alt' => 'Old', 'order' => 0],
            ],
        ]);

        $newGalleryImages = [
            ['url' => '/storage/test-posts/new1.webp', 'alt' => 'New 1', 'order' => 0],
            ['url' => '/storage/test-posts/new2.webp', 'alt' => 'New 2', 'order' => 1],
        ];

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.test-posts.update', $testPost), [
                'title' => $testPost->title,
                'content' => $testPost->content,
                'gallery_images' => $newGalleryImages,
                'status' => $testPost->status,
            ]);

        $response->assertRedirect();

        $testPost->refresh();
        $this->assertCount(2, $testPost->gallery_images);
        $this->assertEquals('/storage/test-posts/new1.webp', $testPost->gallery_images[0]['url']);
    }
}
