<?php

namespace Tests\Feature\Blog;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Blog\Database\Factories\PostTagFactory;
use Nreljed\Blog\Models\PostTag;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class BlogTagTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_list_tags(): void
    {
        $response = $this->actingAs($this->admin())->get(route('dashboard.posts.tags.index'));

        $response->assertStatus(200);
    }

    public function test_admin_can_create_tag(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.tags.store'), [
            'name' => 'Laravel',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('post_tags', ['name' => 'Laravel']);
    }

    public function test_admin_can_update_tag(): void
    {
        $tag = PostTagFactory::new()->create();

        $response = $this->actingAs($this->admin())
            ->put(route('dashboard.posts.tags.update', $tag), [
                'name' => 'Updated Tag',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('post_tags', [
            'id' => $tag->id,
            'name' => 'Updated Tag',
        ]);
    }

    public function test_admin_can_delete_tag(): void
    {
        $tag = PostTagFactory::new()->create();

        $response = $this->actingAs($this->admin())
            ->delete(route('dashboard.posts.tags.destroy', $tag));

        $response->assertRedirect();
        $this->assertDatabaseMissing('post_tags', ['id' => $tag->id]);
    }

    public function test_tag_slug_is_auto_generated(): void
    {
        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.tags.store'), [
            'name' => 'My New Tag',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('post_tags', ['name' => 'My New Tag']);

        $tag = PostTag::where('name', 'My New Tag')->first();
        $this->assertNotNull($tag->slug);
        $this->assertEquals('my-new-tag', $tag->slug);
    }

    public function test_tag_quick_add_returns_json(): void
    {
        $response = $this->actingAs($this->admin())->postJson(route('dashboard.posts.tags.store'), [
            'name' => 'Quick Tag',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['id', 'name']);
        $this->assertDatabaseHas('post_tags', ['name' => 'Quick Tag']);
    }

    public function test_admin_can_reorder_tags(): void
    {
        $a = PostTagFactory::new()->create(['name' => 'Alpha', 'order' => 0]);
        $b = PostTagFactory::new()->create(['name' => 'Beta', 'order' => 1]);

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.tags.reorder'), [
            'ids' => [$b->id, $a->id],
        ]);

        $response->assertRedirect();
        $this->assertEquals(0, $b->fresh()->order);
        $this->assertEquals(1, $a->fresh()->order);
    }

    public function test_bulk_delete_removes_tags(): void
    {
        $a = PostTagFactory::new()->create();
        $b = PostTagFactory::new()->create();

        $response = $this->actingAs($this->admin())->post(route('dashboard.posts.tags.bulk'), [
            'action' => 'delete',
            'ids' => [$a->id, $b->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('post_tags', ['id' => $a->id]);
        $this->assertDatabaseMissing('post_tags', ['id' => $b->id]);
    }
}
