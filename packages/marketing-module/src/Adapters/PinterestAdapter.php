<?php

namespace Nreljed\MarketingModule\Adapters;

use Illuminate\Support\Arr;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Support\PostContent;
use Nreljed\MarketingModule\Support\PublishResult;
use Nreljed\MarketingModule\Support\SocialCapabilities;
use Nreljed\MarketingModule\Support\TokenSet;

/**
 * Pinterest adapter.
 *
 * A Pinterest pin is always an image on a board, so the board has to be
 * chosen before the post can be composed. `discoverAccounts()` lists the
 * user's boards and the account row remembers the selected one as the
 * default board.
 */
class PinterestAdapter extends AbstractSocialAdapter
{
    private const API = 'https://api.pinterest.com/v5';

    public function platform(): string
    {
        return 'pinterest';
    }

    public function label(): string
    {
        return 'Pinterest';
    }

    public function capabilities(): SocialCapabilities
    {
        return new SocialCapabilities(
            supportsText: true,
            supportsImages: true,
            supportsVideo: false,
            supportsMultipleImages: false,
            supportsLink: true,
            supportsHashtags: false,
            requiresMedia: true,
            maxTextLength: 800,
            maxMediaCount: 1,
            allowedMimeTypes: ['image/jpeg', 'image/png'],
        );
    }

    public function discoverAccounts(TokenSet $tokens): array
    {
        $profile = $this->get(self::API.'/user_account', [], $tokens->accessToken);

        if (! $profile->successful()) {
            return [];
        }

        $user = (array) $profile->json();

        $boards = $this->get(self::API.'/boards', [
            'page_size' => 100,
        ], $tokens->accessToken);

        $boardList = [];

        foreach ((array) Arr::get($boards->json(), 'items', []) as $board) {
            if (! is_array($board) || ! isset($board['id'])) {
                continue;
            }

            $boardList[] = [
                'id' => (string) $board['id'],
                'name' => (string) ($board['name'] ?? $board['id']),
            ];
        }

        return [[
            'account_id' => (string) ($user['username'] ?? $user['id'] ?? 'pinterest'),
            'account_name' => (string) ($user['business_name'] ?? $user['username'] ?? 'Pinterest'),
            'username' => isset($user['username']) ? (string) $user['username'] : null,
            'access_token' => $tokens->accessToken,
            'metadata' => [
                'boards' => $boardList,
                'default_board' => $boardList[0]['id'] ?? null,
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
            return PublishResult::failed('media_required', 'A Pinterest pin requires an image.');
        }

        $boardId = $account->boardId();

        if ($boardId === null) {
            return PublishResult::failed('board_missing', 'Choose a Pinterest board before publishing.');
        }

        $payload = [
            'board_id' => $boardId,
            'title' => $this->title($content),
            'description' => $content->text,
            'media_source' => [
                'source_type' => 'image_url',
                'url' => $content->media[0]['url'],
            ],
        ];

        if ($content->link !== null) {
            $payload['link'] = $content->link;
        }

        $response = $this->post(self::API.'/pins', $payload, $token);

        if (! $response->successful()) {
            $error = $this->mapResponse($response);

            return PublishResult::failed($error->code, $error->message, $error->retryable);
        }

        $pinId = Arr::get($response->json(), 'id');

        if (! is_string($pinId) || $pinId === '') {
            return PublishResult::failed('no_post_id', 'Pinterest accepted the pin but returned no id.');
        }

        return PublishResult::published(
            $pinId,
            'https://www.pinterest.com/pin/'.$pinId.'/',
            (array) $response->json(),
        );
    }

    public function fetchMetrics(string $externalPostId, SocialAccount $account): array
    {
        $token = $account->accessToken();

        if ($token === null) {
            return [];
        }

        $response = $this->get(self::API.'/pins/'.$externalPostId.'/analytics', [], $token);

        if (! $response->successful()) {
            return [];
        }

        $metrics = [];

        foreach ((array) $response->json() as $key => $value) {
            if (is_numeric($value)) {
                $metrics[$key] = (int) $value;
            }
        }

        return $metrics;
    }

    /**
     * Pinterest titles are short and standalone; the post body becomes the
     * description.
     */
    protected function title(PostContent $content): string
    {
        $text = trim($content->text);

        if ($text === '') {
            return 'Untitled pin';
        }

        return mb_strlen($text) > 100 ? mb_substr($text, 0, 97).'...' : $text;
    }
}
