<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Nreljed\Blog\Database\Factories\PostFactory;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Models\User;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class CleanupOrphanMediaCommandTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    public function test_deletes_unreferenced_files_and_keeps_referenced(): void
    {
        $admin = $this->admin();

        PostFactory::new()->create([
            'user_id' => $admin->id,
            'featured_image' => '/storage/posts/kept.webp',
        ]);

        Storage::disk('public')->put('posts/kept.webp', 'content');
        Storage::disk('public')->put('posts/orphan.webp', 'content');

        $this->artisan('core:cleanup-orphan-media')
            ->expectsOutputToContain('Deleted 1 orphan file(s).')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('posts/kept.webp'));
        $this->assertFalse(Storage::disk('public')->exists('posts/orphan.webp'));
    }

    public function test_dry_run_does_not_delete_files(): void
    {
        Storage::disk('public')->put('posts/orphan.webp', 'content');

        $this->artisan('core:cleanup-orphan-media', ['--dry-run' => true])
            ->expectsOutputToContain('Would delete 1 orphan file(s).')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('posts/orphan.webp'));
    }

    public function test_reports_no_orphans_when_all_files_referenced(): void
    {
        $admin = $this->admin();

        PostFactory::new()->create([
            'user_id' => $admin->id,
            'featured_image' => '/storage/posts/kept.webp',
        ]);

        Storage::disk('public')->put('posts/kept.webp', 'content');

        $this->artisan('core:cleanup-orphan-media')
            ->expectsOutputToContain('No orphans found in posts/')
            ->expectsOutputToContain('Deleted 0 orphan file(s).')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('posts/kept.webp'));
    }

    public function test_deletes_orphan_avatar_files_and_keeps_referenced(): void
    {
        User::factory()->create(['avatar' => 'kept.webp']);

        Storage::disk('public')->put('avatars/kept.webp', 'content');
        Storage::disk('public')->put('avatars/orphan.webp', 'content');

        $this->artisan('core:cleanup-orphan-media')
            ->expectsOutputToContain('Deleted 1 orphan file(s).')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('avatars/kept.webp'));
        $this->assertFalse(Storage::disk('public')->exists('avatars/orphan.webp'));
    }

    public function test_keeps_post_featured_og_and_gallery_files(): void
    {
        $admin = $this->admin();

        PostFactory::new()->create([
            'user_id' => $admin->id,
            'featured_image' => '/storage/posts/featured.webp',
            'og_image' => '/storage/posts/og.webp',
            'metadata' => [
                'post_gallery' => [
                    ['url' => '/storage/posts/gallery-1.webp'],
                ],
            ],
        ]);

        Storage::disk('public')->put('posts/featured.webp', 'content');
        Storage::disk('public')->put('posts/og.webp', 'content');
        Storage::disk('public')->put('posts/gallery-1.webp', 'content');
        Storage::disk('public')->put('posts/orphan.webp', 'content');

        $this->artisan('core:cleanup-orphan-media')
            ->expectsOutputToContain('Deleted 1 orphan file(s).')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('posts/featured.webp'));
        $this->assertTrue(Storage::disk('public')->exists('posts/og.webp'));
        $this->assertTrue(Storage::disk('public')->exists('posts/gallery-1.webp'));
        $this->assertFalse(Storage::disk('public')->exists('posts/orphan.webp'));
    }
}
