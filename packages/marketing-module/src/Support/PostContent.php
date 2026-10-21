<?php

namespace Nreljed\MarketingModule\Support;

/**
 * The normalised content of a post, independent of any platform.
 *
 * Adapters translate this into their own payload shape. Keeping the
 * canonical form platform-neutral is what lets a composer write one post and
 * target five platforms without five bespoke forms.
 */
final class PostContent
{
    /**
     * @param  list<array{url: string, path?: string, mime?: string, title?: string}>  $media
     * @param  list<string>  $hashtags
     */
    public function __construct(
        public readonly string $text = '',
        public readonly array $media = [],
        public readonly ?string $link = null,
        public readonly array $hashtags = [],
    ) {}

    /**
     * @param  list<array{url: string, path?: string, mime?: string, title?: string}>  $media
     * @param  list<string>  $hashtags
     */
    public static function make(string $text = '', array $media = [], ?string $link = null, array $hashtags = []): self
    {
        return new self($text, $media, $link, $hashtags);
    }

    public function hasMedia(): bool
    {
        return $this->media !== [];
    }

    /**
     * The text a platform should receive, with hashtags folded in.
     */
    public function textWithHashtags(): string
    {
        if ($this->hashtags === []) {
            return $this->text;
        }

        $tags = implode(' ', array_map(
            static fn (string $tag): string => str_starts_with($tag, '#') ? $tag : '#'.$tag,
            $this->hashtags,
        ));

        return trim($this->text.' '.$tags);
    }
}
