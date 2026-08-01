<?php

namespace Tests\Feature\Console;

use Database\Factories\TestPostFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

        TestPostFactory::new()->create([
            'user_id' => $admin->id,
            'featured_image' => '/storage/test-posts/kept.webp',
        ]);

        Storage::disk('public')->put('test-posts/kept.webp', 'content');
        Storage::disk('public')->put('test-posts/orphan.webp', 'content');

        $this->artisan('app:cleanup-orphan-media')
            ->expectsOutputToContain('Deleted 1 orphan file(s).')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('test-posts/kept.webp'));
        $this->assertFalse(Storage::disk('public')->exists('test-posts/orphan.webp'));
    }

    public function test_dry_run_does_not_delete_files(): void
    {
        Storage::disk('public')->put('test-posts/orphan.webp', 'content');

        $this->artisan('app:cleanup-orphan-media', ['--dry-run' => true])
            ->expectsOutputToContain('Would delete 1 orphan file(s).')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('test-posts/orphan.webp'));
    }

    public function test_reports_no_orphans_when_all_files_referenced(): void
    {
        $admin = $this->admin();

        TestPostFactory::new()->create([
            'user_id' => $admin->id,
            'featured_image' => '/storage/test-posts/kept.webp',
        ]);

        Storage::disk('public')->put('test-posts/kept.webp', 'content');

        $this->artisan('app:cleanup-orphan-media')
            ->expectsOutputToContain('No orphans found in test-posts/')
            ->expectsOutputToContain('Deleted 0 orphan file(s).')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('test-posts/kept.webp'));
    }
}
