<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Services\MediaService;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class MediaPathTraversalTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    public function test_delete_image_rejects_parent_traversal(): void
    {
        Storage::disk('public')->put('posts/keep.webp', 'image-data');
        Storage::disk('public')->put('protected/sensitive.txt', 'secret');

        $response = $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.posts.delete-image'), [
                'path' => 'posts/../../protected/sensitive.txt',
            ]);

        $response->assertOk();
        Storage::disk('public')->assertExists('posts/keep.webp');
        Storage::disk('public')->assertExists('protected/sensitive.txt');
    }

    public function test_delete_image_rejects_absolute_paths(): void
    {
        Storage::disk('public')->put('posts/keep.webp', 'image-data');

        foreach (['/etc/passwd', '/storage/posts/keep.webp', 'C:\\Windows\\system32\\drivers\\etc\\hosts'] as $unsafe) {
            $response = $this->actingAs($this->admin())
                ->deleteJson(route('dashboard.posts.delete-image'), ['path' => $unsafe]);

            $response->assertOk();
        }

        Storage::disk('public')->assertExists('posts/keep.webp');
    }

    public function test_delete_image_rejects_encoded_and_backslash_traversal(): void
    {
        Storage::disk('public')->put('posts/keep.webp', 'image-data');
        Storage::disk('public')->put('protected/sensitive.txt', 'secret');

        foreach (['posts\\..\\..\\protected\\sensitive.txt', 'posts/..%2F..%2Fprotected%2Fsensitive.txt'] as $unsafe) {
            $response = $this->actingAs($this->admin())
                ->deleteJson(route('dashboard.posts.delete-image'), ['path' => $unsafe]);

            $response->assertOk();
        }

        Storage::disk('public')->assertExists('protected/sensitive.txt');
    }

    public function test_delete_image_with_empty_path_is_rejected_by_validation(): void
    {
        Storage::disk('public')->put('posts/keep.webp', 'image-data');

        $response = $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.posts.delete-image'), ['path' => '   ']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('path');
        Storage::disk('public')->assertExists('posts/keep.webp');
    }

    public function test_delete_image_legitimate_storage_path_still_deletes(): void
    {
        Storage::disk('public')->put('posts/example.webp', 'image-data');

        $response = $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.posts.delete-image'), ['path' => 'posts/example.webp']);

        $response->assertOk();
        Storage::disk('public')->assertMissing('posts/example.webp');
    }

    public function test_media_service_blocks_unsafe_paths_directly(): void
    {
        Storage::disk('public')->put('posts/keep.webp', 'image-data');
        Storage::disk('public')->put('protected/sensitive.txt', 'secret');

        $service = new MediaService;

        $service->delete('posts/../../protected/sensitive.txt');
        $service->delete('/etc/passwd');
        $service->delete('');

        Storage::disk('public')->assertExists('protected/sensitive.txt');

        $service->delete('posts/keep.webp');

        Storage::disk('public')->assertMissing('posts/keep.webp');
    }

    public function test_post_featured_image_with_traversal_is_sanitized_to_null(): void
    {
        $this->actingAs($this->admin())
            ->post(route('dashboard.posts.store'), [
                'title' => 'Traversal Image',
                'content' => '<p>Body</p>',
                'featured_image' => '/storage/../../.env',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'title' => 'Traversal Image',
            'featured_image' => null,
        ]);
    }
}
