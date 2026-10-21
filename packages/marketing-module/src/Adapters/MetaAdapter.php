<?php

namespace Nreljed\MarketingModule\Adapters;

use Illuminate\Support\Arr;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Support\PostContent;
use Nreljed\MarketingModule\Support\PublishResult;
use Nreljed\MarketingModule\Support\SocialCapabilities;
use Nreljed\MarketingModule\Support\TokenSet;

/**
 * Meta (Facebook Pages) adapter.
 *
 * A Meta login can reach many Pages, and each Page carries its own
 * page-scoped access token — the user token that authorised the connection
 * cannot publish to a Page on its own. `discoverAccounts()` therefore walks
 * /me/accounts and exchanges each Page's user token for a Page token, which
 * is the token stored on the account row.
 */
class MetaAdapter extends AbstractSocialAdapter
{
    private const GRAPH = 'https://graph.facebook.com/v23.0';

    public function platform(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'Meta';
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
            maxTextLength: 63206,
            maxMediaCount: 1,
            allowedMimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4'],
        );
    }

    public function discoverAccounts(TokenSet $tokens): array
    {
        $response = $this->get(self::GRAPH.'/me/accounts', [
            'fields' => 'id,name,username,access_token,fan_count,category',
            'limit' => 100,
        ], $tokens->accessToken);

        if (! $response->successful()) {
            return [];
        }

        $accounts = [];

        foreach ((array) Arr::get($response->json(), 'data', []) as $page) {
            if (! is_array($page) || ! isset($page['id'])) {
                continue;
            }

            $pageToken = $this->pageAccessToken($page, $tokens->accessToken);

            $accounts[] = [
                'account_id' => (string) $page['id'],
                'account_name' => (string) ($page['name'] ?? $page['id']),
                'username' => isset($page['username']) ? (string) $page['username'] : null,
                'access_token' => $pageToken,
                'metadata' => [
                    'fan_count' => $page['fan_count'] ?? null,
                    'category' => $page['category'] ?? null,
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

        $endpoint = self::GRAPH.'/'.$account->account_id.'/feed';

        $payload = ['message' => $content->textWithHashtags()];

        if ($content->link !== null) {
            $payload['link'] = $content->link;
        }

        if ($content->media !== []) {
            // A Page post with an image is published as a photo to the
            // account's /photos endpoint; the /feed endpoint ignores it.
            $endpoint = self::GRAPH.'/'.$account->account_id.'/photos';
            $payload = ['url' => $content->media[0]['url'], 'caption' => $content->textWithHashtags()];
        }

        $response = $this->post($endpoint, $payload, $token);

        if (! $response->successful()) {
            $error = $this->mapResponse($response);

            return PublishResult::failed($error->code, $error->message, $error->retryable);
        }

        $postId = Arr::get($response->json(), 'id');

        if (! is_string($postId) || $postId === '') {
            return PublishResult::failed('no_post_id', 'The platform accepted the post but returned no id.');
        }

        return PublishResult::published(
            $postId,
            'https://www.facebook.com/'.$postId,
            (array) $response->json(),
        );
    }

    public function fetchMetrics(string $externalPostId, SocialAccount $account): array
    {
        $token = $account->accessToken();

        if ($token === null) {
            return [];
        }

        $response = $this->get(self::GRAPH.'/'.$externalPostId.'/insights', [
            'metric' => 'post_impressions,post_reactions_by_type_total,post_clicks',
        ], $token);

        if (! $response->successful()) {
            return [];
        }

        $metrics = [];

        foreach ((array) Arr::get($response->json(), 'data', []) as $entry) {
            $name = Arr::get($entry, 'name');

            if (! is_string($name)) {
                continue;
            }

            $values = Arr::get($entry, 'values', []);
            $total = Arr::get((array) ($values[0] ?? []), 'total', 0);

            $metrics[$this->metricName($name)] = is_numeric($total) ? (int) $total : 0;
        }

        return $metrics;
    }

    /**
     * Exchange the Page's user token for a long-lived Page token.
     */
    protected function pageAccessToken(array $page, ?string $userToken): ?string
    {
        $token = $page['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            return $userToken;
        }

        $response = $this->get(self::GRAPH.'/'.$page['id'].'/access_token', [
            'access_token' => $token,
        ]);

        $issued = Arr::get($response->json(), 'access_token');

        return is_string($issued) && $issued !== '' ? $issued : $token;
    }

    /**
     * Map a Graph metric name onto the module's metric vocabulary.
     */
    protected function metricName(string $graphName): string
    {
        return match ($graphName) {
            'post_impressions' => 'impressions',
            'post_reactions_by_type_total' => 'reactions',
            'post_clicks' => 'clicks',
            default => $graphName,
        };
    }
}
