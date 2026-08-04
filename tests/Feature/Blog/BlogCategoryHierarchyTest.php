<?php

namespace Tests\Feature\Blog;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\DashboardCore\Database\Factories\PostCategoryFactory;
use Nreljed\DashboardCore\Database\Factories\PostFactory;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class BlogCategoryHierarchyTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_store_sets_depth_from_parent(): void
    {
        $parent = PostCategoryFactory::new()->create(['name' => 'Root', 'depth' => 0]);

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.categories.store'), [
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('post_categories', [
            'name' => 'Child',
            'parent_id' => $parent->id,
            'depth' => 1,
        ]);
    }

    public function test_store_quick_add_returns_json(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.categories.store'), [
            'name' => 'Quick Category',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['id', 'name', 'slug']);
        $this->assertDatabaseHas('post_categories', ['name' => 'Quick Category']);
    }

    public function test_reparent_cascades_depth_to_descendants(): void
    {
        $root1 = PostCategoryFactory::new()->create(['name' => 'Root 1', 'depth' => 0]);
        $child = PostCategoryFactory::new()->create(['name' => 'Child', 'parent_id' => $root1->id]);
        $grandchild = PostCategoryFactory::new()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);
        $root2 = PostCategoryFactory::new()->create(['name' => 'Root 2', 'depth' => 0]);

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.posts.categories.update', $root1), [
                'name' => 'Root 1',
                'parent_id' => $root2->id,
            ]);

        $response->assertRedirect();

        $this->assertEquals(1, $root1->fresh()->depth);
        $this->assertEquals(2, $child->fresh()->depth);
        $this->assertEquals(3, $grandchild->fresh()->depth);
    }

    public function test_cannot_make_category_its_own_parent(): void
    {
        $category = PostCategoryFactory::new()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.posts.categories.update', $category), [
                'name' => $category->name,
                'parent_id' => $category->id,
            ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertNull($category->fresh()->parent_id);
    }

    public function test_cannot_make_category_child_of_its_descendant(): void
    {
        $root = PostCategoryFactory::new()->create(['name' => 'Root', 'depth' => 0]);
        $child = PostCategoryFactory::new()->create(['name' => 'Child', 'parent_id' => $root->id]);

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.posts.categories.update', $root), [
                'name' => 'Root',
                'parent_id' => $child->id,
            ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertEquals(0, $root->fresh()->depth);
    }

    public function test_cannot_delete_category_with_posts(): void
    {
        $category = PostCategoryFactory::new()->create();
        PostFactory::new()->create(['category_id' => $category->id]);

        $response = $this->actingAs($this->admin())
            ->delete(route('dashboard.posts.categories.destroy', $category));

        $response->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('post_categories', ['id' => $category->id]);
    }

    public function test_admin_can_reorder_categories(): void
    {
        $a = PostCategoryFactory::new()->create(['name' => 'Alpha', 'order' => 0]);
        $b = PostCategoryFactory::new()->create(['name' => 'Beta', 'order' => 1]);

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.categories.reorder'), [
            'ids' => [$b->id, $a->id],
        ]);

        $response->assertRedirect();
        $this->assertEquals(0, $b->fresh()->order);
        $this->assertEquals(1, $a->fresh()->order);
    }

    public function test_bulk_delete_blocks_categories_with_posts(): void
    {
        $a = PostCategoryFactory::new()->create();
        $b = PostCategoryFactory::new()->create();
        PostFactory::new()->create(['category_id' => $a->id]);

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.categories.bulk'), [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);

        $response->assertSessionHasErrors('bulk');
        $this->assertDatabaseHas('post_categories', ['id' => $a->id]);
        $this->assertDatabaseHas('post_categories', ['id' => $b->id]);
    }

    public function test_bulk_delete_removes_categories_without_posts(): void
    {
        $a = PostCategoryFactory::new()->create();
        $b = PostCategoryFactory::new()->create();

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.categories.bulk'), [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('post_categories', ['id' => $a->id]);
        $this->assertDatabaseMissing('post_categories', ['id' => $b->id]);
    }
}
