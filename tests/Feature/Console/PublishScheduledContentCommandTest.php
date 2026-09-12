<?php

namespace Tests\Feature\Console;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Blog\Database\Factories\PostFactory;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class PublishScheduledContentCommandTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_publishes_due_future_posts(): void
    {
        $due = PostFactory::new()->future()->create([
            'published_at' => now()->subMinute(),
        ]);

        $this->artisan('schedule:publish')
            ->expectsOutputToContain('Published 1 items.')
            ->assertSuccessful();

        $this->assertDatabaseHas('posts', [
            'id' => $due->id,
            'status' => 'published',
        ]);
    }

    public function test_leaves_not_yet_due_future_posts_alone(): void
    {
        $scheduled = PostFactory::new()->future()->create();

        $this->artisan('schedule:publish')
            ->expectsOutputToContain('Published 0 items.')
            ->assertSuccessful();

        $this->assertDatabaseHas('posts', [
            'id' => $scheduled->id,
            'status' => 'future',
        ]);
    }

    public function test_does_not_touch_drafts(): void
    {
        $draft = PostFactory::new()->draft()->create();

        $this->artisan('schedule:publish')->assertSuccessful();

        $this->assertDatabaseHas('posts', [
            'id' => $draft->id,
            'status' => 'draft',
        ]);
    }
}
