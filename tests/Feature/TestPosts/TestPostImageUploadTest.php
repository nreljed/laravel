<?php

namespace Tests\Feature\TestPosts;

use App\TestPosts\Models\TestPost;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nreljed\DashboardCore\Models\Permission;
use Nreljed\DashboardCore\Models\User;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class TestPostImageUploadTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    protected function userWithPermission(string $permissionSlug): User
    {
        $user = $this->regularUser();
        $permission = Permission::where('slug', $permissionSlug)->first();
        $user->permissions()->attach($permission);

        return $user;
    }

    public function test_unauthenticated_upload_returns_401(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600)->size(100);

        $response = $this->postJson(route('dashboard.test-posts.upload-image'), [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_user_without_create_permission_cannot_upload(): void
    {
        $user = $this->regularUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600)->size(100);

        $response = $this->actingAs($user)->postJson(route('dashboard.test-posts.upload-image'), [
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_with_create_permission_can_upload(): void
    {
        $user = $this->userWithPermission('test-posts.create');
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600)->size(100);

        $response = $this->actingAs($user)->postJson(route('dashboard.test-posts.upload-image'), [
            'file' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['url', 'path']);
    }

    public function test_upload_rejects_invalid_file_type(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin())->postJson(route('dashboard.test-posts.upload-image'), [
            'file' => $file,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_upload_requires_file(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.test-posts.upload-image'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_unauthenticated_delete_returns_401(): void
    {
        $response = $this->deleteJson(route('dashboard.test-posts.delete-image'), [
            'path' => 'test-posts/fake.webp',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_without_create_permission_cannot_delete(): void
    {
        $user = $this->regularUser();
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->deleteJson(route('dashboard.test-posts.delete-image'), [
            'path' => 'test-posts/fake.webp',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_with_create_permission_can_delete(): void
    {
        $user = $this->userWithPermission('test-posts.create');
        $fakePath = 'test-posts/example.webp';
        Storage::disk('public')->put($fakePath, 'fake-image-data');

        $response = $this->actingAs($user)->deleteJson(route('dashboard.test-posts.delete-image'), [
            'path' => $fakePath,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);
        Storage::disk('public')->assertMissing($fakePath);
    }

    public function test_delete_requires_path(): void
    {
        $response = $this->actingAs($this->admin())->deleteJson(route('dashboard.test-posts.delete-image'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('path');
    }

    public function test_store_persists_gallery_images_from_upload(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600)->size(100);

        $uploadResponse = $this->actingAs($this->admin())->postJson(route('dashboard.test-posts.upload-image'), [
            'file' => $file,
        ]);

        $uploadResponse->assertOk();
        $imageUrl = $uploadResponse->json('url');

        $galleryImages = [
            ['url' => $imageUrl, 'alt' => 'Test alt', 'caption' => 'Test caption', 'order' => 0],
        ];

        $response = $this->post(route('dashboard.test-posts.store'), [
            'title' => 'Post With Gallery',
            'content' => 'Content.',
            'gallery_images' => $galleryImages,
            'status' => 'draft',
        ]);

        $response->assertRedirect();

        $post = TestPost::where('title', 'Post With Gallery')->first();
        $this->assertNotNull($post);
        $this->assertCount(1, $post->gallery_images);
        $this->assertEquals($imageUrl, $post->gallery_images[0]['url']);
    }
}
