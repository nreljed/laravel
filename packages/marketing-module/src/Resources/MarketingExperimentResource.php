<?php

namespace Nreljed\MarketingModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

final class MarketingExperimentResource
{
    public const KEY = 'marketing.experiments';

    public const ROUTES = [
        'index' => 'dashboard.marketing.experiments.index',
        'create' => 'dashboard.marketing.experiments.create',
        'store' => 'dashboard.marketing.experiments.store',
        'edit' => 'dashboard.marketing.experiments.edit',
        'update' => 'dashboard.marketing.experiments.update',
        'destroy' => 'dashboard.marketing.experiments.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Experiments',
            singular: 'Experiment',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'success_metric', 'header' => 'Metric'],
                    ['name' => 'status_label', 'header' => 'Status', 'sortable' => true],
                    ['name' => 'started_at', 'header' => 'Started'],
                    ['name' => 'ended_at', 'header' => 'Ended'],
                ],
                'searchKeys' => ['name', 'hypothesis'],
                'searchPlaceholder' => 'Search experiments',
            ],
            form: [
                [
                    'title' => 'Details',
                    'fields' => [
                        ['name' => 'marketing_brief_id', 'type' => 'text', 'label' => 'Brief ID'],
                        ['name' => 'campaign_id', 'type' => 'text', 'label' => 'Campaign ID'],
                        ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                        ['name' => 'hypothesis', 'type' => 'textarea', 'label' => 'Hypothesis'],
                        ['name' => 'success_metric', 'type' => 'text', 'label' => 'Success metric', 'required' => true],
                        ['name' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => [
                            ['label' => 'Draft', 'value' => 'draft'],
                            ['label' => 'Running', 'value' => 'running'],
                            ['label' => 'Completed', 'value' => 'completed'],
                            ['label' => 'Cancelled', 'value' => 'cancelled'],
                        ], 'required' => true],
                        ['name' => 'variants', 'type' => 'json', 'label' => 'Variants'],
                        ['name' => 'winner', 'type' => 'text', 'label' => 'Winner'],
                        ['name' => 'started_at', 'type' => 'datetime', 'label' => 'Started at'],
                        ['name' => 'ended_at', 'type' => 'datetime', 'label' => 'Ended at'],
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
