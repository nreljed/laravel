<?php

use Nreljed\MarketingModule\Adapters\InstagramAdapter;
use Nreljed\MarketingModule\Adapters\LinkedInAdapter;
use Nreljed\MarketingModule\Adapters\MetaAdapter;
use Nreljed\MarketingModule\Adapters\PinterestAdapter;
use Nreljed\MarketingModule\Adapters\XAdapter;

return [

    /*
    |--------------------------------------------------------------------------
    | Social channel drivers
    |--------------------------------------------------------------------------
    |
    | Each driver is a class implementing Nreljed\MarketingModule\Contracts\
    | SocialChannelAdapter. The registry resolves them lazily by platform key,
    | so an app that only connects an Instagram account never constructs the
    | Pinterest adapter.
    |
    */

    'channels' => [
        'default' => env('MARKETING_CHANNEL_DRIVER', 'meta'),

        'drivers' => [
            'meta' => MetaAdapter::class,
            'instagram' => InstagramAdapter::class,
            'linkedin' => LinkedInAdapter::class,
            'x' => XAdapter::class,
            'pinterest' => PinterestAdapter::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth applications
    |--------------------------------------------------------------------------
    |
    | Credentials per platform. The Socialite driver name maps to the
    | `services.<driver>` entry Laravel already understands, so these values
    | can live in the host app's config/services.php instead when the app
    | prefers to own them there.
    |
    */

    'oauth' => [
        'apps' => [
            'meta' => [
                'driver' => 'facebook',
                'client_id' => env('MARKETING_META_CLIENT_ID'),
                'client_secret' => env('MARKETING_META_CLIENT_SECRET'),
                'redirect' => env('MARKETING_META_REDIRECT'),
                'graph_version' => env('MARKETING_META_GRAPH_VERSION', 'v23.0'),
                'scopes' => [
                    'pages_show_list',
                    'pages_manage_posts',
                    'pages_read_engagement',
                ],
            ],
            'instagram' => [
                // Instagram has its own login and its own API host
                // (graph.instagram.com). The scopes are the
                // `instagram_business_*` family that replaced the short
                // `business_basic` names in January 2025.
                'driver' => 'instagram',
                'client_id' => env('MARKETING_INSTAGRAM_CLIENT_ID'),
                'client_secret' => env('MARKETING_INSTAGRAM_CLIENT_SECRET'),
                'redirect' => env('MARKETING_INSTAGRAM_REDIRECT'),
                'graph_version' => env('MARKETING_INSTAGRAM_GRAPH_VERSION', 'v23.0'),
                'container_poll_attempts' => 5,
                'container_poll_interval' => 5,
                'scopes' => [
                    'instagram_business_basic',
                    'instagram_business_content_publish',
                    'instagram_business_manage_insights',
                ],
            ],
            'linkedin' => [
                'driver' => 'linkedin',
                'client_id' => env('MARKETING_LINKEDIN_CLIENT_ID'),
                'client_secret' => env('MARKETING_LINKEDIN_CLIENT_SECRET'),
                'redirect' => env('MARKETING_LINKEDIN_REDIRECT'),
                'scopes' => [
                    'openid',
                    'profile',
                    'email',
                    'w_organization_manage',
                    'w_member_social',
                ],
            ],
            'x' => [
                // The `x` driver is Socialite's OAuth 2.0 provider. The
                // `twitter` driver points at the same endpoints but reads
                // its credentials from `services.twitter`.
                'driver' => 'x',
                'client_id' => env('MARKETING_X_CLIENT_ID'),
                'client_secret' => env('MARKETING_X_CLIENT_SECRET'),
                'redirect' => env('MARKETING_X_REDIRECT'),
                'scopes' => [
                    'tweet.read',
                    'tweet.write',
                    'users.read',
                    'offline.access',
                ],
            ],
            'pinterest' => [
                'driver' => 'pinterest',
                'client_id' => env('MARKETING_PINTEREST_CLIENT_ID'),
                'client_secret' => env('MARKETING_PINTEREST_CLIENT_SECRET'),
                'redirect' => env('MARKETING_PINTEREST_REDIRECT'),
                'scopes' => [
                    'user_accounts:read',
                    'pins:read',
                    'pins:write',
                    'boards:read',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Publishing
    |--------------------------------------------------------------------------
    */

    'publishing' => [
        // Attempts per delivery before a post is marked failed.
        'max_attempts' => (int) env('MARKETING_MAX_ATTEMPTS', 3),

        // Backoff (seconds) applied between delivery attempts.
        'retry_backoff' => [60, 300, 900],

        // Default timezone used when a post does not carry one.
        'timezone' => env('MARKETING_TIMEZONE', 'UTC'),

        // Posts to a connected account are held for approval before they go
        // out. On by default so nothing reaches a social audience without a
        // human decision; an install that wants straight-through publishing
        // sets MARKETING_APPROVAL_REQUIRED=false.
        'approval_required' => (bool) env('MARKETING_APPROVAL_REQUIRED', true),

        // Maximum number of platforms a single post may target.
        'max_targets' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    */

    'media' => [
        'subdirectory' => 'social',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audience
    |--------------------------------------------------------------------------
    */

    'audience' => [
        // Maximum contacts a single segment may resolve to before the
        // resolver refuses to run, so a mis-written condition cannot pull the
        // whole contact table into a broadcast.
        'segment_limit' => (int) env('MARKETING_SEGMENT_LIMIT', 50000),
    ],

];
