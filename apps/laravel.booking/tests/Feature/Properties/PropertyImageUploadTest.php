<?php

namespace Tests\Feature\Properties;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nreljed\BookingModule\Properties\Models\Property;
use Tests\BootsFeatures;
use Tests\TestCase;

class PropertyImageUploadTest extends TestCase
{
    use BootsFeatures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootFeatures();
    }

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->clearPermissionCache();

        return $user;
    }

    protected function editorUser(): User
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Property Editor',
            'slug' => 'property_editor',
            'guard_name' => 'web',
        ]);
        $role->permissions()->sync(
            Permission::where('slug', 'properties.view')->pluck('id')
        );
        $user->assignRole($role->slug);

        $this->clearPermissionCache();

        return $user;
    }

    protected function createProperty(int $userId): Property
    {
        return Property::create([
            'user_id' => $userId,
            'name' => 'Test Villa',
            'status' => 'draft',
        ]);
    }

    public function test_ajax_upload_returns_json_with_image_data(): void
    {
        Storage::fake('public');

        $admin = $this->adminUser();
        $property = $this->createProperty($admin->id);

        $response = $this->actingAs($admin)->postJson(
            "/dashboard/properties/{$property->id}/images/upload",
            ['file' => UploadedFile::fake()->image('photo.jpg', 100, 100)],
        );

        $response->assertOk();
        $response->assertJsonStructure(['url', 'path', 'attachment_id']);
        $response->assertJsonPath('attachment_id', $property->images()->first()->id);

        $this->assertDatabaseHas('property_images', [
            'property_id' => $property->id,
        ]);
    }

    public function test_non_ajax_upload_returns_redirect(): void
    {
        Storage::fake('public');

        $admin = $this->adminUser();
        $property = $this->createProperty($admin->id);

        $response = $this->actingAs($admin)->post(
            "/dashboard/properties/{$property->id}/images/upload",
            ['file' => UploadedFile::fake()->image('photo.jpg', 100, 100)],
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('property_images', [
            'property_id' => $property->id,
        ]);
    }

    public function test_upload_rejects_invalid_file(): void
    {
        Storage::fake('public');

        $admin = $this->adminUser();
        $property = $this->createProperty($admin->id);

        $response = $this->actingAs($admin)->postJson(
            "/dashboard/properties/{$property->id}/images/upload",
            ['file' => UploadedFile::fake()->create('document.txt', 10)],
        );

        $response->assertStatus(422);
        $this->assertDatabaseCount('property_images', 0);
    }

    public function test_non_owner_cannot_upload_images(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $property = $this->createProperty($owner->id);
        $editor = $this->editorUser();

        $response = $this->actingAs($editor)->postJson(
            "/dashboard/properties/{$property->id}/images/upload",
            ['file' => UploadedFile::fake()->image('photo.jpg', 100, 100)],
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('property_images', 0);
    }

    public function test_guest_cannot_upload_images(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $property = $this->createProperty($owner->id);

        $response = $this->postJson(
            "/dashboard/properties/{$property->id}/images/upload",
            ['file' => UploadedFile::fake()->image('photo.jpg', 100, 100)],
        );

        $response->assertStatus(401);
    }
}
