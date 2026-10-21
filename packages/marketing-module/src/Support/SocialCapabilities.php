<?php

namespace Nreljed\MarketingModule\Support;

/**
 * What a platform will accept in a post.
 *
 * Platform rules are data, not `if` statements: the composer reads this to
 * warn before a save, the publish job reads it to refuse a post that would
 * be rejected by the API, and the API's own rejection never becomes the
 * first line of defence.
 */
final class SocialCapabilities
{
    /**
     * @param  list<string>  $allowedMimeTypes
     */
    public function __construct(
        public readonly bool $supportsText = true,
        public readonly bool $supportsImages = true,
        public readonly bool $supportsVideo = false,
        public readonly bool $supportsMultipleImages = false,
        public readonly bool $supportsLink = true,
        public readonly bool $supportsHashtags = true,
        public readonly bool $requiresMedia = false,
        public readonly int $maxTextLength = 5000,
        public readonly int $maxMediaCount = 1,
        public readonly array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    ) {}

    /**
     * Whether this platform can carry the given content at all.
     */
    public function accepts(PostContent $content): bool
    {
        if ($content->text === '' && $content->media === [] && $content->link === null) {
            return false;
        }

        if ($content->text !== '' && ! $this->supportsText) {
            return false;
        }

        if ($content->media !== []) {
            if (! $this->supportsImages) {
                return false;
            }

            if (count($content->media) > $this->maxMediaCount) {
                return false;
            }
        } elseif ($this->requiresMedia) {
            return false;
        }

        if ($content->link !== null && ! $this->supportsLink) {
            return false;
        }

        if ($content->hashtags !== [] && ! $this->supportsHashtags) {
            return false;
        }

        return true;
    }

    /**
     * Human-readable reasons the content cannot be published to this platform.
     *
     * @return list<string>
     */
    public function violations(PostContent $content): array
    {
        $problems = [];

        if ($content->text === '' && $content->media === [] && $content->link === null) {
            $problems[] = 'A post needs text, media, or a link.';
        }

        if ($content->text !== '' && ! $this->supportsText) {
            $problems[] = 'This platform does not accept text posts.';
        }

        if ($content->media !== []) {
            if (! $this->supportsImages) {
                $problems[] = 'This platform does not accept media.';
            } elseif (count($content->media) > $this->maxMediaCount) {
                $problems[] = sprintf(
                    'This platform accepts at most %d image(s); %d given.',
                    $this->maxMediaCount,
                    count($content->media),
                );
            }
        } elseif ($this->requiresMedia) {
            $problems[] = 'This platform requires at least one image.';
        }

        if ($content->link !== null && ! $this->supportsLink) {
            $problems[] = 'This platform does not accept a link.';
        }

        if ($content->hashtags !== [] && ! $this->supportsHashtags) {
            $problems[] = 'This platform does not accept hashtags.';
        }

        $length = mb_strlen($content->text);

        if ($length > $this->maxTextLength) {
            $problems[] = sprintf(
                'Text is %d characters; this platform allows %d.',
                $length,
                $this->maxTextLength,
            );
        }

        return $problems;
    }
}
