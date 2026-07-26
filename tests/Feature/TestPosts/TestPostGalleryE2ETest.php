<?php

namespace Tests\Feature\TestPosts;

use App\TestPosts\Models\TestPost;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class TestPostGalleryE2ETest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->storagePath = storage_path('app/public');
        ini_set('memory_limit', '256M');
    }

    private function fakeImage(string $filename = 'test.jpg'): UploadedFile
    {
        return UploadedFile::fake()
            ->image($filename, 800, 600)
            ->size(100);
    }

    private function assertFileOnDisk(string $relativePath): void
    {
        $full = $this->storagePath.'/'.$relativePath;
        $this->assertFileExists($full, "Expected file at {$relativePath}");
    }

    private function assertFileNotOnDisk(string $relativePath): void
    {
        $full = $this->storagePath.'/'.$relativePath;
        $this->assertFileDoesNotExist($full, "Expected file {$relativePath} to be deleted");
    }

    public function test_upload_stores_in_test_posts_folder(): void
    {
        $file = $this->fakeImage('sanibell.jpg');

        $response = $this->actingAs($this->admin())->post(
            route('dashboard.test-posts.upload-image'),
            ['file' => $file],
        );

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('url', $data);
        $this->assertArrayHasKey('path', $data);
        $this->assertStringStartsWith('test-posts/', $data['path'], 'Image must be stored in test-posts/ folder');
        $this->assertStringContainsString('.webp', $data['path'], 'Image should be converted to webp');
        $this->assertStringStartsWith('/storage/test-posts/', $data['url']);

        $this->assertFileOnDisk($data['path']);
    }

    public function test_full_gallery_upload_and_post_create(): void
    {
        $filenames = ['sanibell.jpg', 'charkrit.jpg', 'douglas.jpg'];

        $uploadedImages = [];
        foreach ($filenames as $filename) {
            $file = $this->fakeImage($filename);
            $response = $this->actingAs($this->admin())->post(
                route('dashboard.test-posts.upload-image'),
                ['file' => $file],
            );
            $response->assertOk();
            $uploadedImages[] = $response->json();
        }

        $this->assertCount(3, $uploadedImages, 'All 3 images should upload successfully');

        foreach ($uploadedImages as $i => $img) {
            $this->assertStringStartsWith('test-posts/', $img['path'], "Image {$i} must be in test-posts/ folder");
            $this->assertFileOnDisk($img['path']);
        }

        $galleryPayload = array_map(
            fn (array $img, int $i) => [
                'url' => $img['url'],
                'alt' => "Property image {$i}",
                'caption' => "Caption for image {$i}",
                'order' => $i,
            ],
            $uploadedImages,
            array_keys($uploadedImages)
        );

        $response = $this->post(route('dashboard.test-posts.store'), [
            'title' => 'E2E Gallery Test Post',
            'content' => 'Testing full gallery upload flow.',
            'excerpt' => 'Gallery E2E test.',
            'gallery_images' => $galleryPayload,
            'featured_image' => $uploadedImages[0]['url'],
            'status' => 'published',
        ]);

        $response->assertRedirect();

        $post = TestPost::where('title', 'E2E Gallery Test Post')->first();
        $this->assertNotNull($post, 'Post should be created');
        $this->assertEquals('published', $post->status);
        $this->assertNotNull($post->published_at);

        $this->assertNotNull($post->gallery_images, 'Gallery images should be stored');
        $this->assertCount(3, $post->gallery_images, 'Post should have 3 gallery images');

        foreach ($post->gallery_images as $i => $img) {
            $this->assertStringStartsWith('/storage/test-posts/', $img['url'], "Gallery image {$i} URL must point to test-posts/");
            $this->assertEquals("Property image {$i}", $img['alt']);
            $this->assertEquals("Caption for image {$i}", $img['caption']);
            $this->assertEquals($i, $img['order']);
        }

        $this->assertStringStartsWith('/storage/test-posts/', $post->featured_image);
        $this->assertFileOnDisk('test-posts/'.basename($post->featured_image));
    }

    public function test_gallery_images_ordering_is_preserved(): void
    {
        $uploaded = [];
        for ($i = 0; $i < 3; $i++) {
            $file = $this->fakeImage("order-{$i}.jpg");
            $response = $this->actingAs($this->admin())->post(
                route('dashboard.test-posts.upload-image'),
                ['file' => $file],
            );
            $response->assertOk();
            $uploaded[] = $response->json();
        }

        $gallery = [
            ['url' => $uploaded[2]['url'], 'order' => 0],
            ['url' => $uploaded[0]['url'], 'order' => 1],
            ['url' => $uploaded[1]['url'], 'order' => 2],
        ];

        $this->post(route('dashboard.test-posts.store'), [
            'title' => 'Order Test',
            'gallery_images' => $gallery,
            'status' => 'draft',
        ]);

        $post = TestPost::where('title', 'Order Test')->first();
        $this->assertCount(3, $post->gallery_images);
        $this->assertEquals($uploaded[2]['url'], $post->gallery_images[0]['url']);
        $this->assertEquals($uploaded[0]['url'], $post->gallery_images[1]['url']);
        $this->assertEquals($uploaded[1]['url'], $post->gallery_images[2]['url']);
    }

    public function test_update_replaces_gallery_images(): void
    {
        $file1 = $this->fakeImage('update1.jpg');
        $upload1 = $this->actingAs($this->admin())->post(
            route('dashboard.test-posts.upload-image'),
            ['file' => $file1],
        )->json();

        $this->post(route('dashboard.test-posts.store'), [
            'title' => 'Update Gallery Test',
            'gallery_images' => [
                ['url' => $upload1['url'], 'order' => 0],
            ],
            'status' => 'draft',
        ]);

        $post = TestPost::where('title', 'Update Gallery Test')->first();
        $this->assertCount(1, $post->gallery_images);

        $file2 = $this->fakeImage('update2.jpg');
        $upload2 = $this->actingAs($this->admin())->post(
            route('dashboard.test-posts.upload-image'),
            ['file' => $file2],
        )->json();

        $file3 = $this->fakeImage('update3.jpg');
        $upload3 = $this->actingAs($this->admin())->post(
            route('dashboard.test-posts.upload-image'),
            ['file' => $file3],
        )->json();

        $response = $this->actingAs($this->admin())->put(route('dashboard.test-posts.update', $post), [
            'title' => 'Update Gallery Test',
            'content' => $post->content,
            'gallery_images' => [
                ['url' => $upload2['url'], 'alt' => 'New 1', 'order' => 0],
                ['url' => $upload3['url'], 'alt' => 'New 2', 'order' => 1],
            ],
            'status' => 'draft',
        ]);

        $response->assertRedirect();
        $post->refresh();
        $this->assertCount(2, $post->gallery_images);
        $this->assertEquals('New 1', $post->gallery_images[0]['alt']);
        $this->assertEquals('New 2', $post->gallery_images[1]['alt']);
    }

    public function test_delete_image_removes_from_storage(): void
    {
        $file = $this->fakeImage('delete-me.jpg');
        $upload = $this->actingAs($this->admin())->post(
            route('dashboard.test-posts.upload-image'),
            ['file' => $file],
        )->json();

        $this->assertFileOnDisk($upload['path']);

        $response = $this->actingAs($this->admin())->deleteJson(
            route('dashboard.test-posts.delete-image'),
            ['path' => $upload['path']]
        );

        $response->assertOk();
        $this->assertFileNotOnDisk($upload['path']);
    }
}
