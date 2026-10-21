<?php

namespace Nreljed\MarketingModule\Adapters;

use Illuminate\Support\Arr;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Support\PostContent;
use Nreljed\MarketingModule\Support\PublishResult;
use Nreljed\MarketingModule\Support\SocialCapabilities;
use Nreljed\MarketingModule\Support\TokenSet;

/**
 * Instagram professional account adapter (Instagram API with Instagram Login).
 *
 * The platform moved to its own login: the grant is an *Instagram* user token
 * and the API host is graph.instagram.com, not graph.facebook.com. The
 * scopes are the `instagram_business_*` family for the same reason.
 *
 * Publishing is a two-step container flow. A container is created first and
 * the media id comes back; the container is then published with the
 * container id. Video containers are processed asynchronously by Instagram,
 * so a video waits for the container to report FINISHED before publishing.
 */
class InstagramAdapter extends AbstractSocialAdapter
{
    public function platform(): string
    {
        return 'instagram';
    }

    public function label(): string
    {
        return 'Instagram';
    }

    public function capabilities(): SocialCapabilities
    {
        return new SocialCapabilities(
            supportsText: false,
            supportsImages: true,
            supportsVideo: true,
            supportsMultipleImages: false,
            supportsLink: false,
            supportsHashtags: true,
            requiresMedia: true,
            maxTextLength: 2200,
            maxMediaCount: 1,
            // Instagram only accepts JPEG for feed images. PNG is rejected
            // with an opaque media error, so the composer refuses it up front.
            allowedMimeTypes: ['image/jpeg'],
        );
    }

    /**
     * The Graph host for this platform. Instagram Login has its own host,
     * which is why the Meta adapter cannot be reused here.
     */
    public function graph(): string
    {
        return 'https://graph.instagram.com/'.(string) Arr::get($this->config, 'graph_version', 'v23.0');
    }

    public function discoverAccounts(TokenSet $tokens): array
    {
        $response = $this->get($this->graph().'/me', [
            'fields' => 'id,username,name,biography,profile_picture_url,followers_count,media_count',
        ], $tokens->accessToken);

        if (! $response->successful()) {
            return [];
        }

        $profile = (array) $response->json();

        if (! isset($profile['id']) || $profile['id'] === '') {
            return [];
        }

        return [[
            'account_id' => (string) $profile['id'],
            'account_name' => (string) ($profile['name'] ?? $profile['username'] ?? $profile['id']),
            'username' => isset($profile['username']) ? (string) $profile['username'] : null,
            'access_token' => $tokens->accessToken,
            'metadata' => [
                'biography' => $profile['biography'] ?? null,
                'profile_picture_url' => $profile['profile_picture_url'] ?? null,
                'followers_count' => $profile['followers_count'] ?? null,
            ],
        ]];
    }

    public function publish(PostContent $content, SocialAccount $account): PublishResult
    {
        $token = $account->accessToken();

        if ($token === null) {
            return PublishResult::failed('token_missing', 'The account has no stored access token.');
        }

        if ($content->media === []) {
            return PublishResult::failed('media_required', 'Instagram requires a media file.');
        }

        $media = $content->media[0];
        $isVideo = str_starts_with((string) ($media['mime'] ?? ''), 'video');

        $payload = $isVideo
            ? [
                'video_url' => $media['url'],
                'media_type' => 'REELS',
            ]
            : [
                'image_url' => $media['url'],
            ];

        $payload['caption'] = $content->textWithHashtags();

        $create = $this->post($this->graph().'/'.$account->account_id.'/media', $payload, $token);

        if (! $create->successful()) {
            $error = $this->mapResponse($create);

            return PublishResult::failed($error->code, $error->message, $error->retryable);
        }

        $creationId = Arr::get($create->json(), 'id');

        if (! is_string($creationId) || $creationId === '') {
            return PublishResult::failed('no_creation_id', 'Instagram accepted the media container but returned no id.');
        }

        // Video containers are processed by Instagram before they can be
        // published; image containers are ready immediately.
        if ($isVideo) {
            $status = $this->awaitContainer($creationId, $token);

            if ($status !== 'FINISHED') {
                return PublishResult::failed(
                    'container_not_ready',
                    sprintf('Instagram did not finish processing the media (status: %s).', $status),
                    $status === 'IN_PROGRESS',
                );
            }
        }

        $publish = $this->post($this->graph().'/'.$account->account_id.'/media_publish', [
            'creation_id' => $creationId,
        ], $token);

        if (! $publish->successful()) {
            $error = $this->mapResponse($publish);

            return PublishResult::failed($error->code, $error->message, $error->retryable);
        }

        $postId = Arr::get($publish->json(), 'id');

        return PublishResult::published(
            is_string($postId) ? $postId : $creationId,
            'https://www.instagram.com/p/'.(is_string($postId) ? $postId : $creationId).'/',
            (array) $publish->json(),
        );
    }

    public function fetchMetrics(string $externalPostId, SocialAccount $account): array
    {
        $token = $account->accessToken();

        if ($token === null) {
            return [];
        }

        $response = $this->get($this->graph().'/'.$externalPostId.'/insights', [
            'metric' => 'impressions,reach,likes,comments,saved,shares',
        ], $token);

        if (! $response->successful()) {
            return [];
        }

        $metrics = [];

        foreach ((array) Arr::get($response->json(), 'data', []) as $entry) {
            $name = Arr::get($entry, 'name');
            $values = (array) Arr::get($entry, 'values', []);
            $total = Arr::get((array) ($values[0] ?? []), 'total', 0);

            if (is_string($name) && is_numeric($total)) {
                $metrics[$name] = (int) $total;
            }
        }

        return $metrics;
    }

    /**
     * Poll a container until Instagram reports a terminal status.
     *
     * Returns the last status seen. `IN_PROGRESS` means the caller may retry
     * later; the other terminal statuses are failures.
     */
    protected function awaitContainer(string $containerId, string $token): string
    {
        $attempts = (int) Arr::get($this->config, 'container_poll_attempts', 5);
        $interval = (int) Arr::get($this->config, 'container_poll_interval', 5);

        $status = 'IN_PROGRESS';

        for ($attempt = 0; $attempt < max(1, $attempts); $attempt++) {
            $response = $this->get($this->graph().'/'.$containerId, [
                'fields' => 'status_code',
            ], $token);

            if ($response->successful()) {
                $status = (string) Arr::get($response->json(), 'status_code', 'IN_PROGRESS');
            }

            if ($status !== 'IN_PROGRESS') {
                return $status;
            }

            if ($attempt + 1 < max(1, $attempts)) {
                sleep(max(1, $interval));
            }
        }

        return $status;
    }
}
