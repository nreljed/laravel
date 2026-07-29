<?php

namespace Tests\Feature\Blog;

use App\Models\Permission;
use App\Models\User;
use Database\Factories\PostFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class BlogPermissionsTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_unauthenticated_user_cannot_access_posts(): void
    {
        $response = $this->get(route('dashboard.posts.index'));

        $response->assertRedirect();
    }

    public function test_user_without_posts_view_gets_403(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->get(route('dashboard.posts.index'));

        $response->assertStatus(403);
    }

    public function test_user_with_posts_view_can_see_index(): void
    {
        $user = User::factory()->create();
        $permission = Permission::where('slug', 'posts.view')->first();
        $user->permissions()->attach($permission);

        $response = $this->actingAs($user)->get(route('dashboard.posts.index'));

        $response->assertStatus(200);
    }

    public function test_user_without_posts_create_cannot_store(): void
    {
        $user = $this->regularUser();

        $response = $this->actingAs($user)->post(route('dashboard.posts.store'), [
            'title' => 'Unauthorized Post',
        ]);

        $response->assertStatus(403);
    }

    public function test_non_owner_cannot_edit_post(): void
    {
        $owner = User::factory()->create();
        $permission = Permission::where('slug', 'posts.create')->first();
        $owner->permissions()->attach($permission);

        $post = PostFactory::new()->create(['user_id' => $owner->id]);

        $nonOwner = $this->regularUser();

        $response = $this->actingAs($nonOwner)->get(route('dashboard.posts.edit', $post));

        $response->assertStatus(403);
    }

    public function test_admin_can_edit_any_post(): void
    {
        $owner = User::factory()->create();
        $permission = Permission::where('slug', 'posts.create')->first();
        $owner->permissions()->attach($permission);

        $post = PostFactory::new()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($this->admin())->get(route('dashboard.posts.edit', $post));

        $response->assertStatus(200);
    }
}
