<?php

namespace Nreljed\ShopModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for product categories (plan 6.1 wave-4).
 *
 * The parent_id self-reference needs a recursive picker the kit does not ship
 * yet (plan 5.3); the kernel form covers name/slug/description/sort today and
 * hierarchy editing stays a 5.3 gap noted on the plan.
 */
final class CategoryResource
{
    public const KEY = 'shop.categories';

    public const ROUTES = [
        'index' => 'dashboard.shop.categories.index',
        'create' => 'dashboard.shop.categories.create',
        'store' => 'dashboard.shop.categories.store',
        'edit' => 'dashboard.shop.categories.edit',
        'update' => 'dashboard.shop.categories.update',
        'destroy' => 'dashboard.shop.categories.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Categories',
            singular: 'Category',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'slug', 'header' => 'Slug'],
                    ['name' => 'products_count', 'header' => 'Products', 'align' => 'right'],
                    ['name' => 'is_active', 'header' => 'Active'],
                    ['name' => 'created_at', 'header' => 'Created'],
                ],
                'searchKey' => 'name',
            ],
            form: [
                [
                    'title' => 'Details',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                        ['name' => 'slug', 'type' => 'slug', 'label' => 'Slug', 'help' => 'Blank auto-derives from the name.'],
                        ['name' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                        ['name' => 'sort_order', 'type' => 'number', 'label' => 'Sort Order'],
                        ['name' => 'is_active', 'type' => 'boolean', 'label' => 'Active'],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'shop.manage',
                'create' => 'shop.products.create',
                'edit' => 'shop.products.edit',
                'delete' => 'shop.products.delete',
            ],
        );
    }
}