<?php

namespace Nreljed\MarketingModule\Tests\Unit;

use Nreljed\MarketingModule\Support\PostMedia;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;

class PostMediaTest extends TestCase
{
    use BootsDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();
    }

    public function test_it_normalises_gallery_items_into_the_adapter_shape(): void
    {
        $media = PostMedia::normalize([
            ['url' => '/storage/social/2026/10/one.jpg', 'alt' => 'First', 'order' => 0, 'size' => 4096],
            '/storage/social/2026/10/two.png',
        ]);

        $this->assertSame([
            [
                'url' => '/storage/social/2026/10/one.jpg',
                'path' => 'social/2026/10/one.jpg',
                'mime' => 'image/jpeg',
                'title' => 'First',
                'size' => 4096,
                'order' => 0,
            ],
            [
                'url' => '/storage/social/2026/10/two.png',
                'path' => 'social/2026/10/two.png',
                'mime' => 'image/png',
                'title' => null,
                'size' => 0,
                'order' => 1,
            ],
        ], $media);
    }

    public function test_it_strips_absolute_urls_and_rejects_invalid_entries(): void
    {
        $media = PostMedia::normalize([
            ['url' => 'https://example.test/storage/social/a.webp?cache=1'],
            ['url' => ''],
            ['url' => '/storage/social/../../etc/passwd'],
            ['url' => ''],
        ]);

        $this->assertCount(1, $media);
        $this->assertSame('social/a.webp', $media[0]['path']);
        $this->assertSame('image/webp', $media[0]['mime']);
        $this->assertSame(0, $media[0]['size']);
    }

    public function test_it_rejects_negative_sizes_from_the_gallery_payload(): void
    {
        $media = PostMedia::normalize([
            ['url' => '/storage/social/one.jpg', 'size' => -10],
        ]);

        $this->assertCount(1, $media);
        $this->assertSame(0, $media[0]['size']);
    }

    public function test_it_only_manages_paths_inside_the_social_folder(): void
    {
        $this->assertTrue(PostMedia::isManagedPath('social/2026/10/post.jpg'));
        $this->assertFalse(PostMedia::isManagedPath('posts/2026/10/post.jpg'));
        $this->assertFalse(PostMedia::isManagedPath('socials/post.jpg'));
    }

    public function test_the_profile_stores_jpeg_without_webp_variants(): void
    {
        $profile = PostMedia::profile(7);

        $this->assertSame('social', $profile['folder']);
        $this->assertSame('jpg', $profile['outputExtension']);
        $this->assertSame('image/jpeg', $profile['outputMimeType']);
        $this->assertSame([], $profile['variants']);
        $this->assertStringContainsString('social_7', $profile['filenamePrefix']);
    }
}
