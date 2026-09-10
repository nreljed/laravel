<?php

namespace Tests\Feature\Media;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_unauthenticated_upload_returns_401(): void
    {
        $response = $this->postJson(route('dashboard.media.upload'), [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_upload_image(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->postJson(route('dashboard.media.upload'), [
            'file' => UploadedFile::fake()->image('photo.jpg', 100, 100),
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => ['url', 'path'],
        ]);

        $path = $response->json('data.path');
        $this->assertStringStartsWith('media-library/', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_upload_rejects_non_media_file(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->postJson(route('dashboard.media.upload'), [
            'file' => UploadedFile::fake()->create('document.txt', 10, 'text/plain'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }

    public function test_upload_requires_file(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->postJson(route('dashboard.media.upload'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }

    public function test_authenticated_user_can_delete_media(): void
    {
        $user = $this->regularUser();
        Storage::disk('public')->put('media/to-delete.webp', 'content');

        $response = $this->actingAs($user)->deleteJson(route('dashboard.media.destroy'), [
            'path' => 'media/to-delete.webp',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertFalse(Storage::disk('public')->exists('media/to-delete.webp'));
    }

    public function test_delete_requires_path(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->deleteJson(route('dashboard.media.destroy'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('path');
    }

    public function test_delete_rejects_path_traversal(): void
    {
        $user = $this->regularUser();
        Storage::disk('public')->put('media/keep.webp', 'content');

        $response = $this->actingAs($user)->deleteJson(route('dashboard.media.destroy'), [
            'path' => '../media/keep.webp',
        ]);

        $response->assertOk();
        $this->assertTrue(Storage::disk('public')->exists('media/keep.webp'));
    }

    public function test_delete_rejects_absolute_path(): void
    {
        $user = $this->regularUser();
        Storage::disk('public')->put('media/keep.webp', 'content');

        $response = $this->actingAs($user)->deleteJson(route('dashboard.media.destroy'), [
            'path' => '/media/keep.webp',
        ]);

        $response->assertOk();
        $this->assertTrue(Storage::disk('public')->exists('media/keep.webp'));
    }
}
