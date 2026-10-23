<?php

namespace Nreljed\MarketingModule\Resources;

use Nreljed\MarketingModule\Enums\CampaignObjective;
use Nreljed\MarketingModule\Enums\CampaignStatus;
use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for campaign planning.
 *
 * A campaign groups posts for reporting; it never publishes anything and a
 * post can be reassigned or left without one.
 */
final class CampaignResource
{
    public const KEY = 'marketing.campaigns';

    public const ROUTES = [
        'index' => 'dashboard.marketing.campaigns.index',
        'create' => 'dashboard.marketing.campaigns.create',
        'store' => 'dashboard.marketing.campaigns.store',
        'edit' => 'dashboard.marketing.campaigns.edit',
        'update' => 'dashboard.marketing.campaigns.update',
        'destroy' => 'dashboard.marketing.campaigns.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Campaigns',
            singular: 'Campaign',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'objective_label', 'header' => 'Objective'],
                    ['name' => 'status_label', 'header' => 'Status', 'sortable' => true],
                    ['name' => 'posts_count', 'header' => 'Posts'],
                    ['name' => 'starts_at', 'header' => 'Starts'],
                    ['name' => 'ends_at', 'header' => 'Ends'],
                ],
                'searchKeys' => ['name', 'description'],
                'searchPlaceholder' => 'Search campaigns',
            ],
            form: [
                [
                    'title' => 'Details',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                        [
                            'name' => 'description',
                            'type' => 'textarea',
                            'label' => 'Description',
                            'rows' => 3,
                        ],
                        [
                            'name' => 'objective',
                            'type' => 'select',
                            'label' => 'Objective',
                            'options' => CampaignObjective::options(),
                            'defaultValue' => CampaignObjective::Awareness->value,
                            'required' => true,
                        ],
                        [
                            'name' => 'status',
                            'type' => 'select',
                            'label' => 'Status',
                            'options' => CampaignStatus::options(),
                            'defaultValue' => CampaignStatus::Draft->value,
                            'required' => true,
                        ],
                    ],
                ],
                [
                    'title' => 'Window',
                    'fields' => [
                        [
                            'name' => 'starts_at',
                            'type' => 'datetime',
                            'label' => 'Starts at',
                            'help' => 'Optional. The window the campaign is meant to run in.',
                        ],
                        [
                            'name' => 'ends_at',
                            'type' => 'datetime',
                            'label' => 'Ends at',
                            'help' => 'Optional. Posts can still be scheduled outside this window.',
                        ],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'marketing.campaigns.manage',
                'create' => 'marketing.campaigns.manage',
                'edit' => 'marketing.campaigns.manage',
                'delete' => 'marketing.campaigns.manage',
            ],
        );
    }
}
