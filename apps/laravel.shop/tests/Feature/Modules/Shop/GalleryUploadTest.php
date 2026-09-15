<?php

namespace Tests\Feature\Modules\Shop;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nreljed\ShopModule\Models\Product;
use Tests\BootsFeatures;
use Tests\TestCase;

class GalleryUploadTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();

        $this->admin = User::factory()->create(['email' => 'admin@gallery-test.com']);
        $this->admin->assignRole('admin');
    }

    public function test_upload_image_endpoint_works(): void
    {
        $this->actingAs($this->admin);

        $file = UploadedFile::fake()->image('product-photo.jpg', 500, 500);

        $response = $this->postJson(
            route('dashboard.shop.products.upload-image'),
            ['file' => $file]
        );

        $response->assertOk();
        $response->assertJsonStructure(['url', 'path']);
    }

    public function test_upload_and_store_product_with_image(): void
    {
        $this->actingAs($this->admin);

        // Upload first
        $file = UploadedFile::fake()->image('test.jpg', 800, 600);
        $uploadResponse = $this->postJson(
            route('dashboard.shop.products.upload-image'),
            ['file' => $file]
        );
        $uploadResponse->assertOk();
        $data = $uploadResponse->json();

        // Create product with the uploaded image
        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'Product With Image',
            'price' => 49.99,
            'status' => 'published',
            'images' => [
                ['url' => $data['url'], 'path' => $data['path']],
            ],
            'thumbnail_url' => $data['url'],
        ]);

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('products', ['name' => 'Product With Image']);

        $product = Product::where('name', 'Product With Image')->first();
        $this->assertNotEmpty($product->images);
        $this->assertEquals($data['url'], $product->images[0]['url']);
        $this->assertEquals($data['path'], $product->images[0]['path']);
        $this->assertEquals($data['url'], $product->thumbnail_url);
    }

    public function test_upload_large_image_resizes(): void
    {
        $this->actingAs($this->admin);

        // Create a large image (beyond 1920px)
        $file = UploadedFile::fake()->image('large.jpg', 2000, 1500);

        $response = $this->postJson(
            route('dashboard.shop.products.upload-image'),
            ['file' => $file]
        );

        $response->assertOk();
        $data = $response->json();

        $this->assertNotNull($data['url']);
        $this->assertStringEndsWith('.webp', $data['url']);
    }

    public function test_upload_multiple_images_then_store(): void
    {
        $this->actingAs($this->admin);

        $images = [];
        for ($i = 0; $i < 3; $i++) {
            $file = UploadedFile::fake()->image("photo-{$i}.jpg", 200, 200);
            $resp = $this->postJson(
                route('dashboard.shop.products.upload-image'),
                ['file' => $file]
            );
            $resp->assertOk();
            $images[] = $resp->json();
        }

        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'Multi Image Product',
            'price' => 99.99,
            'status' => 'published',
            'images' => array_map(fn ($img) => ['url' => $img['url'], 'path' => $img['path']], $images),
            'thumbnail_url' => $images[0]['url'],
        ]);

        $response->assertSessionHas('status');
        $product = Product::where('name', 'Multi Image Product')->first();
        $this->assertCount(3, $product->images);
        $this->assertEquals($images[0]['url'], $product->thumbnail_url);
    }

    public function test_delete_image_endpoint_works(): void
    {
        $this->actingAs($this->admin);

        // Create a product first to have a valid entity_id
        $product = Product::create([
            'user_id' => $this->admin->id,
            'name' => 'Test Delete Image',
            'type' => 'simple',
            'price' => 10,
            'status' => 'draft',
        ]);

        // Upload first
        $file = UploadedFile::fake()->image('deletable.jpg', 100, 100);
        $uploadResp = $this->postJson(
            route('dashboard.shop.products.upload-image'),
            ['file' => $file]
        );
        $uploadResp->assertOk();
        $data = $uploadResp->json();

        // Delete via JSON — entity_id is required
        $deleteResp = $this->json('DELETE', route('dashboard.shop.products.delete-image'), [
            'path' => $data['path'],
            'entity_id' => $product->id,
        ]);

        if ($deleteResp->getStatusCode() !== 200) {
            dump('Status: '.$deleteResp->getStatusCode());
            dump('Content: '.$deleteResp->getContent());
        }
        $deleteResp->assertOk();
        $deleteResp->assertJson(['success' => true]);

        // File should be gone
        $this->assertFalse(Storage::disk('public')->exists($data['path']));
    }

    public function test_store_product_without_images_works(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('dashboard.shop.products.store'), [
            'name' => 'No Image Product',
            'price' => 10.00,
            'status' => 'published',
        ]);

        $response->assertSessionHas('status');
        $product = Product::where('name', 'No Image Product')->first();
        $this->assertNull($product->images);
    }

    public function test_upload_invalid_file_type_rejected(): void
    {
        $this->actingAs($this->admin);

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->postJson(
            route('dashboard.shop.products.upload-image'),
            ['file' => $file]
        );

        $response->assertStatus(422);
    }

    public function test_upload_requires_authentication(): void
    {
        $file = UploadedFile::fake()->image('unauth.jpg', 100, 100);

        $response = $this->postJson(
            route('dashboard.shop.products.upload-image'),
            ['file' => $file]
        );

        $response->assertStatus(401); // JSON request to web middleware returns 401
    }
}
