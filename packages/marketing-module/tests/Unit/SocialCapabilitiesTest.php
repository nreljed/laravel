<?php

namespace Nreljed\MarketingModule\Tests\Unit;

use Nreljed\MarketingModule\Support\PostContent;
use Nreljed\MarketingModule\Support\SocialCapabilities;
use PHPUnit\Framework\TestCase;

class SocialCapabilitiesTest extends TestCase
{
    public function test_it_rejects_media_formats_the_platform_does_not_accept(): void
    {
        $capabilities = new SocialCapabilities(
            allowedMimeTypes: ['image/jpeg'],
            maxMediaCount: 4,
        );

        $content = PostContent::make('Hello', [
            ['url' => '/storage/social/a.webp', 'mime' => 'image/webp'],
        ]);

        $this->assertFalse($capabilities->accepts($content));
        $this->assertContains(
            'This platform does not accept image/webp files.',
            $capabilities->violations($content),
        );
    }

    public function test_it_accepts_an_allowed_mime_and_ignores_items_without_one(): void
    {
        $capabilities = new SocialCapabilities(
            allowedMimeTypes: ['image/jpeg'],
            maxMediaCount: 4,
        );

        $this->assertTrue($capabilities->accepts(PostContent::make('Hello', [
            ['url' => '/storage/social/a.jpg', 'mime' => 'image/jpeg'],
            ['url' => '/storage/social/b.jpg'],
        ])));
    }

    public function test_it_rejects_video_where_video_is_unsupported(): void
    {
        $capabilities = new SocialCapabilities(
            supportsVideo: false,
            allowedMimeTypes: ['image/jpeg', 'video/mp4'],
        );

        $this->assertFalse($capabilities->accepts(PostContent::make('Hello', [
            ['url' => '/storage/social/a.mp4', 'mime' => 'video/mp4'],
        ])));
    }
}
