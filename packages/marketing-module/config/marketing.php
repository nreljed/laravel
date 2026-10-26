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
    | Links
    |--------------------------------------------------------------------------
    |
    | Tracked links are served from a public prefix outside the dashboard. A
    | dedicated base URL can keep them on a short host; otherwise the app URL
    | is used. Tracking itself is opt-in per post.
    |
    */

    'links' => [
        'prefix' => trim((string) env('MARKETING_LINKS_PREFIX', 'm'), '/'),
        'base_url' => env('MARKETING_LINKS_BASE_URL'),
        'code_length' => (int) env('MARKETING_LINKS_CODE_LENGTH', 8),
        'rate_limit_per_minute' => (int) env('MARKETING_LINKS_RATE_LIMIT', 60),
        'retention_days' => (int) env('MARKETING_LINKS_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Engagement collection ships disabled until a consumer has verified the
    | provider scopes. A rejected grant is recorded per delivery rather than
    | zero-filling the report.
    |
    */

    'metrics' => [
        'enabled' => (bool) env('MARKETING_METRICS_ENABLED', false),
        'sync_days' => (int) env('MARKETING_METRICS_SYNC_DAYS', 30),
        'sync_interval_hours' => (int) env('MARKETING_METRICS_SYNC_INTERVAL_HOURS', 6),
        'retry_after_minutes' => (int) env('MARKETING_METRICS_RETRY_AFTER_MINUTES', 60),
        'max_per_run' => (int) env('MARKETING_METRICS_MAX_PER_RUN', 100),
        'requests_per_minute' => (int) env('MARKETING_METRICS_REQUESTS_PER_MINUTE', 30),
        'retention_days' => (int) env('MARKETING_METRICS_RETENTION_DAYS', 365),

        // Governance: a failed delivery is retried this many times before it
        // is parked as a dead letter. Non-retryable provider errors dead-letter
        // immediately.
        'max_attempts' => (int) env('MARKETING_METRICS_MAX_ATTEMPTS', 5),

        // Governance: provider calls allowed per platform per UTC day. 0 means
        // no daily cap. The counter lives in the application cache.
        'daily_request_budget' => (int) env('MARKETING_METRICS_DAILY_BUDGET', 0),

        // Per-platform overrides, merged over the defaults above:
        //   'linkedin' => [
        //       'requests_per_minute' => 10,
        //       'max_per_run' => 25,
        //       'daily_request_budget' => 500,
        //   ],
        'platforms' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email
    |--------------------------------------------------------------------------
    */

    'email' => [
        'driver' => env('MARKETING_EMAIL_DRIVER', 'log'),
    ],

    'sms' => [
        'driver' => env('MARKETING_SMS_DRIVER', 'log'),
    ],

    'whatsapp' => [
        'driver' => env('MARKETING_WHATSAPP_DRIVER', 'log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    */

    'media' => [
        'subdirectory' => 'social',

        // The composer's gallery enforces these; the upload endpoint repeats
        // the size check server-side.
        'max_files' => 4,
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'output_quality' => 85,
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

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | `marketing:prune` uses these windows. Workflow runs are the audit trail of
    | every automation step, so they keep a longer default window than the
    | high-volume metric and click tables.
    |
    */

    'workflows' => [
        'retention_days' => (int) env('MARKETING_WORKFLOW_RUN_RETENTION_DAYS', 180),
    ],

];
