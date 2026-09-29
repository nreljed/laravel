<?php

namespace Nreljed\ShopModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for product brands (plan 6.1 wave-4).
 */
final class BrandResource
{
    public const KEY = 'shop.brands';

    public const ROUTES = [
        'index' => 'dashboard.shop.brands.index',
        'create' => 'dashboard.shop.brands.create',
        'store' => 'dashboard.shop.brands.store',
        'edit' => 'dashboard.shop.brands.edit',
        'update' => 'dashboard.shop.brands.update',
        'destroy' => 'dashboard.shop.brands.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Brands',
            singular: 'Brand',
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
                        ['name' => 'image', 'type' => 'media', 'label' => 'Logo'],
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