<?php

namespace Nreljed\MarketingModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

final class MarketingBriefResource
{
    public const KEY = 'marketing.briefs';

    public const ROUTES = [
        'index' => 'dashboard.marketing.briefs.index',
        'create' => 'dashboard.marketing.briefs.create',
        'store' => 'dashboard.marketing.briefs.store',
        'edit' => 'dashboard.marketing.briefs.edit',
        'update' => 'dashboard.marketing.briefs.update',
        'destroy' => 'dashboard.marketing.briefs.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Briefs',
            singular: 'Brief',
            table: [
                'columns' => [
                    ['name' => 'title', 'header' => 'Title', 'sortable' => true],
                    ['name' => 'plan_name', 'header' => 'Plan'],
                    ['name' => 'status_label', 'header' => 'Status', 'sortable' => true],
                    ['name' => 'due_at', 'header' => 'Due'],
                ],
                'searchKeys' => ['title', 'key_message'],
                'searchPlaceholder' => 'Search briefs',
            ],
            form: [
                [
                    'title' => 'Details',
                    'fields' => [
                        ['name' => 'marketing_plan_id', 'type' => 'text', 'label' => 'Plan ID', 'help' => 'Optional plan this brief belongs to.'],
                        ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true],
                        ['name' => 'objective', 'type' => 'select', 'label' => 'Objective', 'options' => [
                            ['label' => 'Awareness', 'value' => 'awareness'],
                            ['label' => 'Engagement', 'value' => 'engagement'],
                            ['label' => 'Leads', 'value' => 'leads'],
                            ['label' => 'Sales', 'value' => 'sales'],
                        ]],
                        ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => [
                            ['label' => 'Draft', 'value' => 'draft'],
                            ['label' => 'Ready', 'value' => 'ready'],
                            ['label' => 'Used', 'value' => 'used'],
                            ['label' => 'Archived', 'value' => 'archived'],
                        ], 'required' => true],
                        ['name' => 'audience', 'type' => 'textarea', 'label' => 'Audience'],
                        ['name' => 'key_message', 'type' => 'textarea', 'label' => 'Key message'],
                        ['name' => 'channels', 'type' => 'json', 'label' => 'Channels'],
                        ['name' => 'due_at', 'type' => 'datetime', 'label' => 'Due at'],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'marketing.plans.manage',
                'create' => 'marketing.plans.manage',
                'edit' => 'marketing.plans.manage',
                'delete' => 'marketing.plans.manage',
            ],
        );
    }
}
