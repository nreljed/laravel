<?php

namespace Nreljed\MarketingModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for audience segments.
 *
 * A segment is a saved filter over the contact table. The condition tree is
 * edited as JSON in v1 — the structured editor arrives with the workflow
 * builder, which needs the same condition vocabulary.
 */
final class SegmentResource
{
    public const KEY = 'marketing.segments';

    public const ROUTES = [
        'index' => 'dashboard.marketing.segments.index',
        'create' => 'dashboard.marketing.segments.create',
        'store' => 'dashboard.marketing.segments.store',
        'edit' => 'dashboard.marketing.segments.edit',
        'update' => 'dashboard.marketing.segments.update',
        'destroy' => 'dashboard.marketing.segments.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Segments',
            singular: 'Segment',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'description', 'header' => 'Description'],
                    ['name' => 'is_active', 'header' => 'Active'],
                    ['name' => 'created_at', 'header' => 'Created'],
                ],
                'searchKey' => 'name',
                'searchPlaceholder' => 'Search segments',
            ],
            form: [
                [
                    'title' => 'Details',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                        ['name' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                        [
                            'name' => 'conditions',
                            'type' => 'json',
                            'label' => 'Conditions',
                            'help' => 'A list of OR groups, each holding a list of AND conditions.',
                        ],
                        ['name' => 'is_active', 'type' => 'boolean', 'label' => 'Active'],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'marketing.segments.manage',
                'create' => 'marketing.segments.manage',
                'edit' => 'marketing.segments.manage',
                'delete' => 'marketing.segments.manage',
            ],
        );
    }
}
