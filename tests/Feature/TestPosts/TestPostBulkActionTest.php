<?php

namespace Tests\Feature\TestPosts;

use Database\Factories\TestPostFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Nreljed\DashboardCore\Models\Permission;
use Nreljed\DashboardCore\Models\Role;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class TestPostBulkActionTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_bulk_action_requires_authentication(): void
    {
        $testPost = TestPostFactory::new()->draft()->create();

        $response = $this->postJson(route('dashboard.test-posts.bulk.action'), [
            'ids' => [$testPost->id],
            'action' => 'publish',
        ]);

        $response->assertStatus(401);
    }

    public function test_bulk_action_requires_valid_ids(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.test-posts.bulk.action'), [
            'ids' => [9999],
            'action' => 'publish',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ids.0');
    }

    public function test_bulk_action_requires_valid_action(): void
    {
        $testPost = TestPostFactory::new()->create();

        $response = $this->actingAs($this->admin())->postJson(route('dashboard.test-posts.bulk.action'), [
            'ids' => [$testPost->id],
            'action' => 'invalid_action',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('action');
    }

    public function test_bulk_action_requires_ids_array(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.test-posts.bulk.action'), [
            'ids' => 'not_an_array',
            'action' => 'publish',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ids');
    }

    public function test_bulk_action_with_empty_ids_returns_error(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.test-posts.bulk.action'), [
            'ids' => [],
            'action' => 'publish',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('ids');
    }

    public function test_bulk_publish_sets_status_and_published_at(): void
    {
        $testPosts = TestPostFactory::new()->draft()->count(3)->create();

        $response = $this->actingAs($this->admin())->post(route('dashboard.test-posts.bulk.action'), [
            'ids' => $testPosts->pluck('id')->toArray(),
            'action' => 'publish',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', '3 test post(s) updated to published.');

        foreach ($testPosts as $testPost) {
            $this->assertDatabaseHas('test_posts', [
                'id' => $testPost->id,
                'status' => 'published',
            ]);
            $this->assertNotNull($testPost->fresh()->published_at);
        }
    }

    public function test_bulk_draft_sets_status_and_clears_published_at(): void
    {
        $testPosts = TestPostFactory::new()->published()->count(3)->create();

        $response = $this->actingAs($this->admin())->post(route('dashboard.test-posts.bulk.action'), [
            'ids' => $testPosts->pluck('id')->toArray(),
            'action' => 'draft',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', '3 test post(s) updated to draft.');

        foreach ($testPosts as $testPost) {
            $this->assertDatabaseHas('test_posts', [
                'id' => $testPost->id,
                'status' => 'draft',
            ]);
            $this->assertNull($testPost->fresh()->published_at);
        }
    }

    public function test_bulk_delete_removes_test_posts(): void
    {
        $testPosts = TestPostFactory::new()->count(3)->create();
        $testPostIds = $testPosts->pluck('id')->toArray();

        $response = $this->actingAs($this->admin())->post(route('dashboard.test-posts.bulk.action'), [
            'ids' => $testPostIds,
            'action' => 'delete',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', '3 test post(s) deleted successfully.');

        foreach ($testPostIds as $id) {
            $this->assertDatabaseMissing('test_posts', ['id' => $id]);
        }
    }

    public function test_bulk_delete_removes_featured_and_gallery_files(): void
    {
        Storage::fake('public');

        $testPosts = TestPostFactory::new()->count(2)->create([
            'featured_image' => '/storage/test-posts/featured.webp',
            'gallery_images' => [
                ['url' => '/storage/test-posts/gallery-1.webp'],
            ],
        ]);
        Storage::disk('public')->put('test-posts/featured.webp', 'image');
        Storage::disk('public')->put('test-posts/gallery-1.webp', 'image');

        $response = $this->actingAs($this->admin())->post(route('dashboard.test-posts.bulk.action'), [
            'ids' => $testPosts->pluck('id')->toArray(),
            'action' => 'delete',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.success', '2 test post(s) deleted successfully.');

        Storage::disk('public')->assertMissing('test-posts/featured.webp');
        Storage::disk('public')->assertMissing('test-posts/gallery-1.webp');
    }

    public function test_bulk_delete_with_mixed_ownership_only_deletes_authorized(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'test_editor'],
            ['name' => 'Test Editor', 'description' => 'Can delete test posts']
        );
        $role->permissions()->sync(Permission::whereIn('slug', ['test-posts.delete'])->pluck('id'));

        $user = $this->createUserWithRole('test_editor');
        $otherUser = $this->regularUser();

        $userTestPost = TestPostFactory::new()->create(['user_id' => $user->id]);
        $otherTestPost = TestPostFactory::new()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post(route('dashboard.test-posts.bulk.action'), [
            'ids' => [$userTestPost->id, $otherTestPost->id],
            'action' => 'delete',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('test_posts', ['id' => $userTestPost->id]);
        $this->assertDatabaseHas('test_posts', ['id' => $otherTestPost->id]);
    }

    public function test_regular_user_without_test_posts_edit_cannot_bulk_update_status(): void
    {
        $user = $this->regularUser();
        $testPost = TestPostFactory::new()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('dashboard.test-posts.bulk.action'), [
            'ids' => [$testPost->id],
            'action' => 'publish',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash.error', 'You are not authorized to update the selected test posts.');
    }

    public function test_single_update_status_to_published(): void
    {
        $testPost = TestPostFactory::new()->draft()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.test-posts.update-status', $testPost), [
                'status' => 'published',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('test_posts', [
            'id' => $testPost->id,
            'status' => 'published',
        ]);
        $this->assertNotNull($testPost->fresh()->published_at);
    }

    public function test_single_update_status_to_draft(): void
    {
        $testPost = TestPostFactory::new()->published()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.test-posts.update-status', $testPost), [
                'status' => 'draft',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('test_posts', [
            'id' => $testPost->id,
            'status' => 'draft',
        ]);
        $this->assertNull($testPost->fresh()->published_at);
    }

    public function test_single_delete_test_post(): void
    {
        $testPost = TestPostFactory::new()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('dashboard.test-posts.destroy', $testPost));

        $response->assertRedirect();
        $this->assertDatabaseMissing('test_posts', ['id' => $testPost->id]);
    }
}
