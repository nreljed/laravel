<?php

namespace Nreljed\MarketingModule\Adapters;

use Illuminate\Support\Arr;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Support\PostContent;
use Nreljed\MarketingModule\Support\PublishResult;
use Nreljed\MarketingModule\Support\SocialCapabilities;
use Nreljed\MarketingModule\Support\TokenSet;

/**
 * LinkedIn Pages (organizations) adapter.
 *
 * Posting to an organization goes through the UGC Post API, which needs the
 * organization id rather than the member id the login resolves to — hence
 * `discoverAccounts()` walking /organization and the member's
 * `organizationRoles` to find the ones this member can post for.
 */
class LinkedInAdapter extends AbstractSocialAdapter
{
    private const API = 'https://api.linkedin.com/rest';

    public function platform(): string
    {
        return 'linkedin';
    }

    public function label(): string
    {
        return 'LinkedIn';
    }

    public function capabilities(): SocialCapabilities
    {
        return new SocialCapabilities(
            supportsText: true,
            supportsImages: true,
            supportsVideo: true,
            supportsMultipleImages: false,
            supportsLink: true,
            supportsHashtags: true,
            requiresMedia: false,
            maxTextLength: 3000,
            maxMediaCount: 1,
            allowedMimeTypes: ['image/jpeg', 'image/png', 'image/gif'],
        );
    }

    public function discoverAccounts(TokenSet $tokens): array
    {
        $organizations = $this->get(self::API.'/organization', [
            'q' => 'owner',
            'viewer' => 'member',
            'count' => 100,
            'projection' => '(elements*(id,name,vanityName,logo(lifecycleState)))',
        ], $tokens->accessToken);

        if (! $organizations->successful()) {
            return [];
        }

        $accounts = [];

        foreach ((array) Arr::get($organizations->json(), 'elements', []) as $organization) {
            if (! is_array($organization) || ! isset($organization['id'])) {
                continue;
            }

            $accounts[] = [
                'account_id' => (string) $organization['id'],
                'account_name' => (string) ($organization['name'] ?? $organization['id']),
                'username' => isset($organization['vanityName']) ? (string) $organization['vanityName'] : null,
                'access_token' => $tokens->accessToken,
                'metadata' => [
                    'logo' => Arr::get($organization, 'logo.lifecycleState'),
                ],
            ];
        }

        return $accounts;
    }

    public function publish(PostContent $content, SocialAccount $account): PublishResult
    {
        $token = $account->accessToken();

        if ($token === null) {
            return PublishResult::failed('token_missing', 'The account has no stored access token.');
        }

        $payload = [
            'author' => 'urn:li:organization:'.$account->account_id,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => $content->textWithHashtags(),
                    ],
                    'shareMediaCategory' => $this->mediaCategory($content),
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $response = $this->post(self::API.'/posts', $payload, $token);

        if (! $response->successful()) {
            $error = $this->mapResponse($response);

            return PublishResult::failed($error->code, $error->message, $error->retryable);
        }

        $postId = Arr::get($response->json(), 'id');

        if (! is_string($postId) || $postId === '') {
            return PublishResult::failed('no_post_id', 'LinkedIn accepted the post but returned no id.');
        }

        return PublishResult::published(
            $postId,
            'https://www.linkedin.com/feed/update/urn:li:share:'.$postId,
            (array) $response->json(),
        );
    }

    public function fetchMetrics(string $externalPostId, SocialAccount $account): array
    {
        $token = $account->accessToken();

        if ($token === null) {
            return [];
        }

        $response = $this->get(self::API.'/socialActions/'.$externalPostId, [
            'q' => 'member',
            'count' => 100,
        ], $token);

        if (! $response->successful()) {
            return [];
        }

        $elements = (array) Arr::get($response->json(), 'elements', []);

        $likes = 0;
        $comments = 0;

        foreach ($elements as $element) {
            $type = strtolower((string) Arr::get($element, 'type'));

            if ($type === 'like' || $type === 'reaction') {
                $likes++;
            }

            if ($type === 'comment') {
                $comments++;
            }
        }

        return array_filter([
            'reactions' => $likes,
            'comments' => $comments,
        ], static fn (int $value): bool => $value > 0);
    }

    /**
     * LinkedIn requires a media category even for a text-only post.
     *
     * @return array<string, mixed>
     */
    protected function mediaCategory(PostContent $content): array
    {
        if ($content->media === []) {
            return [
                'media' => [
                    'status' => 'READY',
                ],
            ];
        }

        return [
            'media' => [
                'status' => 'READY',
                'originalUrl' => $content->media[0]['url'],
                'title' => $content->media[0]['title'] ?? '',
            ],
        ];
    }
}
