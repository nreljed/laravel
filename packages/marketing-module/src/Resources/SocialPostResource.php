<?php

namespace Nreljed\MarketingModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for the composer.
 *
 * Phase 2 ships one content type - text, link, hashtags - addressed to the
 * accounts the author connected. Media rides the same post: the composer
 * uploads JPEG images, the adapters receive them through `PostContent`.
 */
final class SocialPostResource
{
    public const KEY = 'marketing.posts';

    public const ROUTES = [
        'index' => 'dashboard.marketing.posts.index',
        'create' => 'dashboard.marketing.posts.create',
        'store' => 'dashboard.marketing.posts.store',
        'edit' => 'dashboard.marketing.posts.edit',
        'update' => 'dashboard.marketing.posts.update',
        'destroy' => 'dashboard.marketing.posts.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Posts',
            singular: 'Post',
            table: [
                'columns' => [
                    ['name' => 'preview', 'header' => 'Post'],
                    ['name' => 'status', 'header' => 'Status', 'sortable' => true],
                    ['name' => 'delivery', 'header' => 'Delivered'],
                    ['name' => 'scheduled_at', 'header' => 'Scheduled', 'sortable' => true],
                    ['name' => 'published_at', 'header' => 'Published'],
                ],
                'searchKeys' => ['preview'],
                'searchPlaceholder' => 'Search posts',
                'rowActions' => [
                    [
                        'label' => 'Approve',
                        'routeName' => 'dashboard.marketing.posts.approve',
                        'method' => 'post',
                    ],
                    [
                        'label' => 'Publish now',
                        'routeName' => 'dashboard.marketing.posts.publish',
                        'method' => 'post',
                        'confirm' => 'Publish this post to its targets now?',
                    ],
                ],
            ],
            form: [
                [
                    'title' => 'Content',
                    'fields' => [
                        [
                            'name' => 'body',
                            'type' => 'textarea',
                            'label' => 'Post text',
                            'rows' => 6,
                            'required' => true,
                        ],
                        ['name' => 'link', 'type' => 'text', 'label' => 'Link'],
                        [
                            'name' => 'track_clicks',
                            'type' => 'boolean',
                            'label' => 'Track clicks',
                            'defaultValue' => false,
                            'help' => 'Create a tracked link for each platform and add UTM parameters. The post keeps the original URL.',
                        ],
                        [
                            'name' => 'media',
                            'type' => 'media',
                            'label' => 'Images',
                            'multiple' => true,
                            'maxImages' => 4,
                            'uploadUrl' => 'dashboard.marketing.posts.media.upload',
                            'deleteUrl' => 'dashboard.marketing.posts.media.destroy',
                            'subdirectory' => 'social',
                            'help' => 'Up to four images. Stored as JPEG for social delivery.',
                        ],
                        [
                            'name' => 'hashtags',
                            'type' => 'text',
                            'label' => 'Hashtags',
                            'help' => 'Comma-separated, without the #.',
                        ],
                    ],
                ],
                [
                    'title' => 'Publishing',
                    'fields' => [
                        [
                            'name' => 'account_ids',
                            'type' => 'checkboxList',
                            'label' => 'Publish to',
                            'optionsUrl' => 'dashboard.marketing.posts.account-options',
                            'required' => true,
                            'help' => 'Only the accounts you connected are listed.',
                        ],
                        [
                            'name' => 'campaign_id',
                            'type' => 'select',
                            'label' => 'Campaign',
                            'optionsUrl' => 'dashboard.marketing.campaigns.options',
                            'help' => 'Optional. Groups the post for reporting without changing when it runs.',
                        ],
                        [
                            'name' => 'scheduled_at',
                            'type' => 'datetime',
                            'label' => 'Scheduled for',
                            'help' => 'Leave empty to keep the post as a draft.',
                        ],
                        [
                            'name' => 'requires_approval',
                            'type' => 'boolean',
                            'label' => 'Requires approval',
                            'defaultValue' => true,
                            'help' => 'Hold the post until someone approves it.',
                        ],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'marketing.posts.manage',
                'create' => 'marketing.posts.manage',
                'edit' => 'marketing.posts.manage',
                'delete' => 'marketing.posts.manage',
            ],
        );
    }
}
