<?php

namespace Tests\Feature\Modules\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nreljed\Platform\Models\Media;
use Nreljed\Platform\Services\UploadProfiles;
use Tests\BootsFeatures;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use BootsFeatures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFeatures();
        Storage::fake('public');
    }

    private function pngUpload(): UploadedFile
    {
        return UploadedFile::fake()->image('hero.png', 800, 600);
    }

    private function monthSegment(): string
    {
        return '/'.now()->format('Y').'/'.now()->format('m').'/';
    }

    public function test_post_image_upload_records_media_row_with_profile_layout(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->post(route('dashboard.posts.upload-image'), [
            'file' => $this->pngUpload(),
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['path', 'url', 'filename']);

        $media = Media::where('folder', 'posts')->first();
        $this->assertNotNull($media);
        $this->assertSame($admin->id, $media->user_id);
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertStringContainsString('post_'.$admin->id.'_', $media->filename);
        $this->assertStringContainsString($this->monthSegment(), $media->path);

        Storage::disk('public')->assertExists($media->path);
        foreach ([1200, 800, 400] as $width) {
            $variant = str_replace('\\', '/', dirname($media->path))
                .'/'.pathinfo($media->path, PATHINFO_FILENAME)."-{$width}.webp";
            Storage::disk('public')->assertExists($variant);
        }
    }

    public function test_media_controller_upload_uses_media_library_profile(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->post(route('dashboard.media.upload'), [
            'file' => $this->pngUpload(),
            'folder' => 'banners',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.folder', 'banners');

        $media = Media::where('folder', 'banners')->first();
        $this->assertNotNull($media);
        $this->assertStringContainsString('media_'.$admin->id.'_', $media->filename);
        $this->assertStringContainsString($this->monthSegment(), $media->path);
        $this->assertSame($media->id, $response->json('data.id'));
    }

    public function test_media_upload_rejects_non_image_content(): void
    {
        $admin = $this->createUserWithRole('admin');

        $response = $this->actingAs($admin)->post(route('dashboard.media.upload'), [
            'file' => UploadedFile::fake()->create('notes.txt', 100),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(0, Media::count());
    }

    public function test_media_index_scopes_non_managers_to_their_own_uploads(): void
    {
        $admin = $this->createUserWithRole('admin');
        $author = $this->createUserWithRole('user');

        Media::create([
            'user_id' => $admin->id, 'folder' => 'posts', 'path' => 'posts/a.webp',
            'url' => '/storage/posts/a.webp', 'filename' => 'a.webp', 'mime_type' => 'image/webp', 'size' => 100,
        ]);
        Media::create([
            'user_id' => $author->id, 'folder' => 'posts', 'path' => 'posts/b.webp',
            'url' => '/storage/posts/b.webp', 'filename' => 'b.webp', 'mime_type' => 'image/webp', 'size' => 100,
        ]);

        $authorResponse = $this->actingAs($author)->getJson(route('dashboard.media.index', ['folder' => 'posts']));
        $authorResponse->assertOk();
        $authorResponse->assertJsonPath('total', 1);
        $authorResponse->assertJsonPath('data.0.id', $author->id);

        $adminResponse = $this->actingAs($admin)->getJson(route('dashboard.media.index', ['folder' => 'posts']));
        $adminResponse->assertOk();
        $adminResponse->assertJsonPath('total', 2);
    }

    public function test_upload_profiles_expose_expected_config(): void
    {
        $posts = UploadProfiles::posts(7);
        $this->assertSame('posts', $posts['folder']);
        $this->assertSame('post_7', $posts['filenamePrefix']);
        $this->assertSame([1200, 800, 400], $posts['variants']);
        $this->assertTrue($posts['organizeByMonth']);
        $this->assertContains('image/webp', $posts['allowedMimes']);

        $avatar = UploadProfiles::avatar(3);
        $this->assertSame('avatars', $avatar['folder']);
        $this->assertSame('user_3', $avatar['filenamePrefix']);
        $this->assertFalse($avatar['organizeByMonth']);

        $media = UploadProfiles::mediaLibrary('banners', 5);
        $this->assertSame('banners', $media['folder']);
        $this->assertSame('media_5', $media['filenamePrefix']);
    }
}
