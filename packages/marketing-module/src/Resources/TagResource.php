<?php

namespace Nreljed\MarketingModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for contact tags.
 *
 * Tags are the labels automations match on, so they are the one part of the
 * audience that a workflow author touches directly.
 */
final class TagResource
{
    public const KEY = 'marketing.tags';

    public const ROUTES = [
        'index' => 'dashboard.marketing.tags.index',
        'create' => 'dashboard.marketing.tags.create',
        'store' => 'dashboard.marketing.tags.store',
        'edit' => 'dashboard.marketing.tags.edit',
        'update' => 'dashboard.marketing.tags.update',
        'destroy' => 'dashboard.marketing.tags.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Tags',
            singular: 'Tag',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'slug', 'header' => 'Slug'],
                    ['name' => 'color', 'header' => 'Color'],
                    ['name' => 'created_at', 'header' => 'Created'],
                ],
                'searchKey' => 'name',
                'searchPlaceholder' => 'Search tags',
            ],
            form: [
                [
                    'title' => 'Details',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                        ['name' => 'slug', 'type' => 'slug', 'label' => 'Slug', 'help' => 'Blank auto-derives from the name.'],
                        ['name' => 'color', 'type' => 'text', 'label' => 'Color', 'help' => 'A CSS colour, e.g. #4f46e5.'],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'marketing.tags.manage',
                'create' => 'marketing.tags.manage',
                'edit' => 'marketing.tags.manage',
                'delete' => 'marketing.tags.manage',
            ],
        );
    }
}
