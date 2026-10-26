<?php

namespace Nreljed\MarketingModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for marketing plans and briefs.
 */
final class MarketingPlanResource
{
    public const KEY = 'marketing.plans';

    public const ROUTES = [
        'index' => 'dashboard.marketing.plans.index',
        'create' => 'dashboard.marketing.plans.create',
        'store' => 'dashboard.marketing.plans.store',
        'edit' => 'dashboard.marketing.plans.edit',
        'update' => 'dashboard.marketing.plans.update',
        'destroy' => 'dashboard.marketing.plans.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Plans',
            singular: 'Plan',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'objective_label', 'header' => 'Objective'],
                    ['name' => 'status_label', 'header' => 'Status', 'sortable' => true],
                    ['name' => 'cadence', 'header' => 'Cadence'],
                    ['name' => 'starts_at', 'header' => 'Starts'],
                    ['name' => 'ends_at', 'header' => 'Ends'],
                ],
                'searchKeys' => ['name'],
                'searchPlaceholder' => 'Search plans',
            ],
            form: [
                [
                    'title' => 'Details',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                        ['name' => 'objective', 'type' => 'select', 'label' => 'Objective', 'options' => [
                            ['label' => 'Awareness', 'value' => 'awareness'],
                            ['label' => 'Engagement', 'value' => 'engagement'],
                            ['label' => 'Leads', 'value' => 'leads'],
                            ['label' => 'Sales', 'value' => 'sales'],
                        ], 'required' => true],
                        ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => [
                            ['label' => 'Draft', 'value' => 'draft'],
                            ['label' => 'Approved', 'value' => 'approved'],
                            ['label' => 'Archived', 'value' => 'archived'],
                        ], 'required' => true],
                        ['name' => 'cadence', 'type' => 'text', 'label' => 'Cadence', 'help' => 'e.g. 3 posts/week'],
                        ['name' => 'audience', 'type' => 'textarea', 'label' => 'Audience'],
                        ['name' => 'constraints', 'type' => 'textarea', 'label' => 'Constraints'],
                    ],
                ],
                [
                    'title' => 'Window',
                    'fields' => [
                        ['name' => 'starts_at', 'type' => 'datetime', 'label' => 'Starts at'],
                        ['name' => 'ends_at', 'type' => 'datetime', 'label' => 'Ends at'],
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
