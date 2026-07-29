<?php

namespace Tests\Feature;

use App\TestPosts\Models\TestPost;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class TestPostFeaturedImageTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_featured_image_auto_set_from_first_gallery_on_create(): void
    {
        $admin = $this->admin();

        $galleryImages = [
            ['url' => '/storage/test-posts/img1.webp', 'alt' => 'Image 1', 'order' => 0],
            ['url' => '/storage/test-posts/img2.webp', 'alt' => 'Image 2', 'order' => 1],
        ];

        $response = $this->actingAs($admin)->post(route('dashboard.test-posts.store'), [
            'title' => 'Test Gallery Featured',
            'content' => 'Content',
            'status' => 'draft',
            'featured_image' => '',
            'gallery_images' => $galleryImages,
        ]);

        $response->assertRedirect();

        $post = TestPost::where('title', 'Test Gallery Featured')->first();
        $this->assertNotNull($post);
        $this->assertEquals('/storage/test-posts/img1.webp', $post->featured_image);
    }

    public function test_featured_image_not_overridden_if_explicitly_set(): void
    {
        $admin = $this->admin();

        $galleryImages = [
            ['url' => '/storage/test-posts/img1.webp', 'alt' => 'Image 1', 'order' => 0],
            ['url' => '/storage/test-posts/img2.webp', 'alt' => 'Image 2', 'order' => 1],
        ];

        $response = $this->actingAs($admin)->post(route('dashboard.test-posts.store'), [
            'title' => 'Test Explicit Featured',
            'content' => 'Content',
            'status' => 'draft',
            'featured_image' => '/storage/test-posts/custom-featured.webp',
            'gallery_images' => $galleryImages,
        ]);

        $response->assertRedirect();

        $post = TestPost::where('title', 'Test Explicit Featured')->first();
        $this->assertNotNull($post);
        $this->assertEquals('/storage/test-posts/custom-featured.webp', $post->featured_image);
    }

    public function test_featured_image_auto_set_on_update_when_null(): void
    {
        $admin = $this->admin();

        $post = TestPost::create([
            'user_id' => $admin->id,
            'title' => 'Update Test',
            'slug' => 'update-test-abc',
            'status' => 'draft',
            'featured_image' => null,
        ]);

        $galleryImages = [
            ['url' => '/storage/test-posts/new1.webp', 'alt' => 'New 1', 'order' => 0],
        ];

        $response = $this->actingAs($admin)->put(route('dashboard.test-posts.update', $post->id), [
            'title' => 'Update Test',
            'content' => 'Content',
            'status' => 'draft',
            'featured_image' => '',
            'gallery_images' => $galleryImages,
        ]);

        $response->assertRedirect();

        $post->refresh();
        $this->assertEquals('/storage/test-posts/new1.webp', $post->featured_image);
    }

    public function test_no_featured_image_when_gallery_empty(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('dashboard.test-posts.store'), [
            'title' => 'No Gallery',
            'content' => 'Content',
            'status' => 'draft',
            'featured_image' => '',
            'gallery_images' => [],
        ]);

        $response->assertRedirect();

        $post = TestPost::where('title', 'No Gallery')->first();
        $this->assertNotNull($post);
        $this->assertNull($post->featured_image);
    }
}
