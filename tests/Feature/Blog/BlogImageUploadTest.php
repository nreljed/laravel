<?php

namespace Tests\Feature\Blog;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nreljed\DashboardCore\Models\Permission;
use Nreljed\DashboardCore\Models\User;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class BlogImageUploadTest extends TestCase
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

        $response = $this->postJson(route('dashboard.posts.upload-image'), [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_user_without_create_permission_cannot_upload(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['email_verified_at' => now()])->save();
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600)->size(100);

        $response = $this->actingAs($user)->postJson(route('dashboard.posts.upload-image'), [
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_with_create_permission_can_upload(): void
    {
        $user = $this->userWithPermission('posts.create');
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600)->size(100);

        $response = $this->actingAs($user)->postJson(route('dashboard.posts.upload-image'), [
            'file' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['url', 'path']);
        $path = $response->json('path');
        $this->assertStringStartsWith('posts/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_rejects_invalid_file_type(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.upload-image'), [
            'file' => $file,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_upload_requires_file(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.upload-image'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_unauthenticated_delete_returns_401(): void
    {
        $response = $this->deleteJson(route('dashboard.posts.delete-image'), [
            'path' => 'posts/fake.webp',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_without_edit_permission_cannot_delete(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->deleteJson(route('dashboard.posts.delete-image'), [
            'path' => 'posts/fake.webp',
        ]);

        $response->assertStatus(403);
    }

    public function test_editor_can_delete_uploaded_image(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600)->size(100);
        $upload = $this->actingAs($this->admin())->postJson(route('dashboard.posts.upload-image'), [
            'file' => $file,
        ]);
        $upload->assertOk();
        $path = $upload->json('path');

        $response = $this->actingAs($this->editor())->deleteJson(route('dashboard.posts.delete-image'), [
            'path' => $path,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_user_with_edit_permission_but_not_create_can_delete(): void
    {
        $user = $this->userWithPermission('posts.edit');
        $fakePath = 'posts/example.webp';
        Storage::disk('public')->put($fakePath, 'fake-image-data');

        $response = $this->actingAs($user)->deleteJson(route('dashboard.posts.delete-image'), [
            'path' => $fakePath,
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['success' => true]);
        Storage::disk('public')->assertMissing($fakePath);
    }

    public function test_delete_requires_path(): void
    {
        $response = $this->actingAs($this->admin())->deleteJson(route('dashboard.posts.delete-image'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('path');
    }
}
