<?php

namespace Nreljed\MarketingModule\Services;

/**
 * Turns a platform's metric payload into the module's one vocabulary.
 *
 * The normaliser never manufactures a number: missing keys stay null and
 * unknown numeric keys are kept in `custom` rather than guessed into a
 * first-class column.
 */
class MetricNormalizer
{
    /**
     * @var list<string>
     */
    public const CANONICAL = [
        'impressions',
        'reach',
        'likes',
        'comments',
        'shares',
        'saves',
        'clicks',
        'video_views',
        'replies',
        'reposts',
        'quotes',
        'bookmarks',
        'profile_visits',
    ];

    /**
     * Provider names that have an unambiguous canonical meaning.
     *
     * @var array<string, string>
     */
    protected const ALIASES = [
        'post_impressions' => 'impressions',
        'post_impressions_unique' => 'reach',
        'impression' => 'impressions',
        'impression_count' => 'impressions',
        'post_reactions_by_type_total' => 'likes',
        'reaction' => 'likes',
        'reactions' => 'likes',
        'like_count' => 'likes',
        'comment' => 'comments',
        'comment_count' => 'comments',
        'save' => 'saves',
        'saved' => 'saves',
        'save_count' => 'saves',
        'post_clicks' => 'clicks',
        'click_count' => 'clicks',
        'outbound_click' => 'clicks',
        'video_view' => 'video_views',
        'video_view_count' => 'video_views',
        'post_video_views' => 'video_views',
        'reply_count' => 'replies',
        'retweet_count' => 'reposts',
        'repost_count' => 'reposts',
        'repin' => 'shares',
        'quote_count' => 'quotes',
        'bookmark_count' => 'bookmarks',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     metrics: array<string, int|null>,
     *     custom: array<string, int>,
     *     raw: array<string, mixed>
     * }
     */
    public function normalize(array $payload): array
    {
        $metrics = array_fill_keys(self::CANONICAL, null);
        $custom = [];

        // Read exact canonical names first so an alias can never overwrite a
        // more specific value the provider supplied.
        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! is_numeric($value)) {
                continue;
            }

            $name = strtolower($key);

            if (in_array($name, self::CANONICAL, true)) {
                $metrics[$name] = (int) $value;
            }
        }

        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! is_numeric($value)) {
                continue;
            }

            $name = strtolower($key);

            if (in_array($name, self::CANONICAL, true)) {
                continue;
            }

            $canonical = self::ALIASES[$name] ?? null;

            if ($canonical === null) {
                $custom[$name] = (int) $value;

                continue;
            }

            if ($metrics[$canonical] === null) {
                $metrics[$canonical] = (int) $value;
            }
        }

        return [
            'metrics' => $metrics,
            'custom' => $custom,
            'raw' => $this->sanitize($payload),
        ];
    }

    /**
     * Remove credential-like keys and bound long strings before persisting.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitize(array $payload): array
    {
        return $this->sanitizeArray($payload, 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizeArray(array $payload, int $depth): array
    {
        if ($depth >= 8) {
            return [];
        }

        $clean = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && preg_match('/token|secret|authorization|password|api[_-]?key|credential/i', $key)) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeArray($value, $depth + 1);

                continue;
            }

            if (is_string($value)) {
                $clean[$key] = mb_substr($value, 0, 500);

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
