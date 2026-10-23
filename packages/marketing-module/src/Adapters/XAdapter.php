<?php

namespace Nreljed\MarketingModule\Adapters;

use Illuminate\Support\Arr;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Support\PostContent;
use Nreljed\MarketingModule\Support\PublishResult;
use Nreljed\MarketingModule\Support\SocialCapabilities;
use Nreljed\MarketingModule\Support\TokenSet;

/**
 * X (Twitter) adapter, API v2.
 *
 * Media is uploaded first to obtain a media id, which the post then
 * references. Public engagement metrics for other users' posts are gated
 * behind elevated X API access, so `fetchMetrics()` returns only what the
 * current access tier exposes rather than inventing numbers.
 */
class XAdapter extends AbstractSocialAdapter
{
    private const API = 'https://api.x.com/2';

    public function platform(): string
    {
        return 'x';
    }

    public function label(): string
    {
        return 'X';
    }

    public function capabilities(): SocialCapabilities
    {
        return new SocialCapabilities(
            supportsText: true,
            supportsImages: true,
            supportsVideo: true,
            supportsMultipleImages: true,
            supportsLink: true,
            supportsHashtags: true,
            requiresMedia: false,
            maxTextLength: 280,
            maxMediaCount: 4,
            allowedMimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4'],
        );
    }

    public function discoverAccounts(TokenSet $tokens): array
    {
        $response = $this->get(self::API.'/users/me', [
            'user.fields' => 'id,name,username,profile_image_url,public_metrics',
        ], $tokens->accessToken);

        if (! $response->successful()) {
            return [];
        }

        $data = Arr::get($response->json(), 'data');

        if (! is_array($data) || ! isset($data['id'])) {
            return [];
        }

        return [[
            'account_id' => (string) $data['id'],
            'account_name' => (string) ($data['name'] ?? $data['id']),
            'username' => isset($data['username']) ? (string) $data['username'] : null,
            'access_token' => $tokens->accessToken,
            'metadata' => [
                'profile_image_url' => $data['profile_image_url'] ?? null,
                'followers_count' => Arr::get($data, 'public_metrics.followers_count'),
            ],
        ]];
    }

    public function publish(PostContent $content, SocialAccount $account): PublishResult
    {
        $token = $account->accessToken();

        if ($token === null) {
            return PublishResult::failed('token_missing', 'The account has no stored access token.');
        }

        $payload = ['text' => $content->textWithHashtags()];

        $mediaIds = $this->uploadMedia($content, $token);

        if ($mediaIds === []) {
            $error = $this->lastMediaError;

            if ($error !== null) {
                return $error;
            }
        } else {
            $payload['media'] = ['media_ids' => $mediaIds];
        }

        $response = $this->post(self::API.'/tweets', $payload, $token);

        if (! $response->successful()) {
            $error = $this->mapResponse($response);

            return PublishResult::failed($error->code, $error->message, $error->retryable);
        }

        $postId = Arr::get($response->json(), 'data.id');

        if (! is_string($postId) || $postId === '') {
            return PublishResult::failed('no_post_id', 'X accepted the post but returned no id.');
        }

        return PublishResult::published(
            $postId,
            'https://x.com/i/web/status/'.$postId,
            (array) $response->json(),
        );
    }

    public function fetchMetrics(string $externalPostId, SocialAccount $account): array
    {
        $token = $account->accessToken();

        if ($token === null) {
            $this->throwMetricsTokenMissing();
        }

        $response = $this->get(self::API.'/tweets/'.$externalPostId, [
            'tweet.fields' => 'public_metrics',
        ], $token);

        if (! $response->successful()) {
            $this->throwMetricsFailure($response);
        }

        $metrics = Arr::get($response->json(), 'data.public_metrics');

        if (! is_array($metrics) || $metrics === []) {
            return [];
        }

        $normalized = [];

        foreach ([
            'impression_count' => 'impressions',
            'like_count' => 'likes',
            'reply_count' => 'replies',
            'retweet_count' => 'reposts',
            'quote_count' => 'quotes',
            'bookmark_count' => 'bookmarks',
        ] as $source => $target) {
            if (array_key_exists($source, $metrics) && is_numeric($metrics[$source])) {
                $normalized[$target] = (int) $metrics[$source];
            }
        }

        return $normalized;
    }

    /**
     * The failure from the most recent media upload, if one happened.
     */
    protected ?PublishResult $lastMediaError = null;

    /**
     * Upload every attached media file and return the resulting media ids.
     *
     * @return list<string>
     */
    protected function uploadMedia(PostContent $content, string $token): array
    {
        $this->lastMediaError = null;
        $ids = [];

        foreach ($content->media as $media) {
            $url = (string) ($media['url'] ?? '');

            if ($url === '') {
                continue;
            }

            $response = $this->post(self::API.'/media/upload', [
                'media' => $url,
            ], $token);

            if (! $response->successful()) {
                $error = $this->mapResponse($response);
                $this->lastMediaError = PublishResult::failed($error->code, $error->message, $error->retryable);

                return [];
            }

            $id = Arr::get($response->json(), 'data.id');

            if (is_string($id) && $id !== '') {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
