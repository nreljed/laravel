<?php

namespace Tests\Feature\Blog;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Blog\Database\Factories\PostCategoryFactory;
use Nreljed\Blog\Models\PostCategory;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class BlogCategoryTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_list_categories(): void
    {
        $response = $this->actingAs($this->admin())->get(route('dashboard.posts.categories.index'));

        $response->assertStatus(200);
    }

    public function test_admin_can_create_category(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.categories.store'), [
            'name' => 'Technology',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('post_categories', ['name' => 'Technology']);
    }

    public function test_admin_can_update_category(): void
    {
        $category = PostCategoryFactory::new()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.posts.categories.update', $category), [
                'name' => 'Science',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('post_categories', [
            'id' => $category->id,
            'name' => 'Science',
        ]);
    }

    public function test_admin_can_delete_category(): void
    {
        $category = PostCategoryFactory::new()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('dashboard.posts.categories.destroy', $category));

        $response->assertRedirect();
        $this->assertDatabaseMissing('post_categories', ['id' => $category->id]);
    }

    public function test_category_slug_is_auto_generated(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.categories.store'), [
            'name' => 'Health & Wellness',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('post_categories', ['name' => 'Health & Wellness']);

        $category = PostCategory::where('name', 'Health & Wellness')->first();
        $this->assertNotNull($category->slug);
        $this->assertEquals('health-wellness', $category->slug);
    }
}
