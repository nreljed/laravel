<?php

namespace Tests\Feature\Modules\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Nreljed\Platform\Models\Media;
use Tests\BootsFeatures;
use Tests\TestCase;

class MediaOrphanCleanupTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();
        Storage::fake('public');
    }

    private function makeRow(int $userId, string $path): Media
    {
        return Media::create([
            'user_id' => $userId,
            'folder' => 'posts',
            'path' => $path,
            'url' => '/storage/'.$path,
            'filename' => basename($path),
            'mime_type' => 'image/webp',
            'size' => 100,
        ]);
    }

    private function makeFile(string $path, string $contents = 'data'): void
    {
        Storage::disk('public')->put($path, $contents);
    }

    public function test_scan_reports_missing_records_and_orphan_files_for_manager(): void
    {
        $admin = $this->createUserWithRole('admin');

        // Present file + row (not an orphan).
        $this->makeFile('posts/2026/09/ok.webp');
        $this->makeRow($admin->id, 'posts/2026/09/ok.webp');

        // Row with no file on disk (dangling record).
        $this->makeRow($admin->id, 'posts/2026/09/gone.webp');

        // Unrecorded file on disk (orphan).
        $this->makeFile('posts/2026/09/stray.png');

        // Variant of a recorded primary — never an orphan.
        $this->makeFile('posts/2026/09/recorded.webp');
        $this->makeRow($admin->id, 'posts/2026/09/recorded.webp');
        $this->makeFile('posts/2026/09/recorded-800.webp');

        // Variant with no primary anywhere — an orphan.
        $this->makeFile('posts/2026/09/solo-1200.webp');

        $response = $this->actingAs($admin)->postJson(route('dashboard.media.scan'), [
            'folder' => 'posts',
        ]);

        $response->assertOk();
        $response->assertJsonPath('totals.missing_records', 1);
        $response->assertJsonPath('totals.orphan_files', 2);
        $response->assertJsonPath('records.0.path', 'posts/2026/09/gone.webp');

        $orphanPaths = collect($response->json('orphan_files'))->pluck('path')->sort()->values();
        $this->assertEquals(
            collect(['posts/2026/09/solo-1200.webp', 'posts/2026/09/stray.png'])->sort()->values(),
            $orphanPaths
        );
    }

    public function test_scan_scopes_missing_records_to_own_user_and_hides_orphan_files_for_non_manager(): void
    {
        $admin = $this->createUserWithRole('admin');
        $author = $this->createUserWithRole('user');

        $this->makeRow($admin->id, 'posts/2026/09/admin-gone.webp');
        $this->makeRow($author->id, 'posts/2026/09/author-gone.webp');
        $this->makeFile('posts/2026/09/stray.png');

        $response = $this->actingAs($author)->postJson(route('dashboard.media.scan'), [
            'folder' => 'posts',
        ]);

        $response->assertOk();
        $response->assertJsonPath('totals.missing_records', 1);
        $response->assertJsonPath('records.0.path', 'posts/2026/09/author-gone.webp');
        $response->assertJsonPath('totals.orphan_files', 0);
        $response->assertJsonPath('orphan_files', []);
        $this->assertSame(2, Media::count());
    }

    public function test_cleanup_records_removes_only_missing_rows_for_manager(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->makeFile('posts/2026/09/ok.webp');
        $this->makeRow($admin->id, 'posts/2026/09/ok.webp');
        $this->makeRow($admin->id, 'posts/2026/09/gone.webp');

        $response = $this->actingAs($admin)->postJson(route('dashboard.media.cleanup'), [
            'folder' => 'posts',
            'mode' => 'records',
        ]);

        $response->assertOk();
        $response->assertJsonPath('removed_records', 1);
        $response->assertJsonPath('removed_files', 0);
        $this->assertSame(1, Media::count());
        $this->assertSame('posts/2026/09/ok.webp', Media::first()->path);
        Storage::disk('public')->assertExists('posts/2026/09/ok.webp');
    }

    public function test_cleanup_files_deletes_only_unrecorded_files(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->makeFile('posts/2026/09/ok.webp');
        $this->makeRow($admin->id, 'posts/2026/09/ok.webp');
        $this->makeFile('posts/2026/09/stray.png');
        $this->makeFile('posts/2026/09/solo-400.webp');
        $this->makeFile('posts/2026/09/reserved.txt');

        $response = $this->actingAs($admin)->postJson(route('dashboard.media.cleanup'), [
            'folder' => 'posts',
            'mode' => 'files',
        ]);

        $response->assertOk();
        $response->assertJsonPath('removed_files', 2);
        Storage::disk('public')->assertExists('posts/2026/09/ok.webp');
        Storage::disk('public')->assertMissing('posts/2026/09/stray.png');
        Storage::disk('public')->assertMissing('posts/2026/09/solo-400.webp');
        Storage::disk('public')->assertExists('posts/2026/09/reserved.txt');
        $this->assertSame(1, Media::count());
    }

    public function test_cleanup_both_removes_records_and_files(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->makeFile('posts/2026/09/ok.webp');
        $this->makeRow($admin->id, 'posts/2026/09/ok.webp');
        $this->makeRow($admin->id, 'posts/2026/09/gone.webp');
        $this->makeFile('posts/2026/09/stray.png');

        $response = $this->actingAs($admin)->postJson(route('dashboard.media.cleanup'), [
            'folder' => 'posts',
            'mode' => 'both',
        ]);

        $response->assertOk();
        $response->assertJsonPath('removed_records', 1);
        $response->assertJsonPath('removed_files', 1);
        $this->assertSame(1, Media::count());
        Storage::disk('public')->assertExists('posts/2026/09/ok.webp');
        Storage::disk('public')->assertMissing('posts/2026/09/stray.png');
    }

    public function test_non_manager_cannot_cleanup_files(): void
    {
        $author = $this->createUserWithRole('user');
        $this->makeFile('posts/2026/09/stray.png');

        $this->actingAs($author)
            ->postJson(route('dashboard.media.cleanup'), ['folder' => 'posts', 'mode' => 'files'])
            ->assertForbidden();

        $this->actingAs($author)
            ->postJson(route('dashboard.media.cleanup'), ['folder' => 'posts', 'mode' => 'both'])
            ->assertForbidden();

        Storage::disk('public')->assertExists('posts/2026/09/stray.png');
    }

    public function test_non_manager_cleanup_records_only_affects_own_rows(): void
    {
        $admin = $this->createUserWithRole('admin');
        $author = $this->createUserWithRole('user');

        $this->makeRow($admin->id, 'posts/2026/09/admin-gone.webp');
        $this->makeRow($author->id, 'posts/2026/09/author-gone.webp');

        $response = $this->actingAs($author)->postJson(route('dashboard.media.cleanup'), [
            'folder' => 'posts',
            'mode' => 'records',
        ]);

        $response->assertOk();
        $response->assertJsonPath('removed_records', 1);
        $this->assertSame(1, Media::count());
        $this->assertSame('posts/2026/09/admin-gone.webp', Media::first()->path);
    }

    public function test_scan_rejects_invalid_folder(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('dashboard.media.scan'), ['folder' => '../etc'])
            ->assertStatus(422);
    }

    public function test_cleanup_rejects_unknown_mode(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->postJson(route('dashboard.media.cleanup'), ['folder' => 'posts', 'mode' => 'nuke'])
            ->assertStatus(422);
    }
}
