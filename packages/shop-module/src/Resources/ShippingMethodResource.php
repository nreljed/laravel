<?php

namespace Nreljed\ShopModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for shipping methods (plan 6.1 wave-3).
 *
 * Shipping zones stay on hand-rolled pages until the kernel kit grows the
 * relation() picker (plan 5.3) their pivot needs; shipping methods are a
 * plain flat table and convert cleanly on the kernel path.
 */
final class ShippingMethodResource
{
    public const KEY = 'shop.shipping-methods';

    public const ROUTES = [
        'index' => 'dashboard.shop.shipping-methods.index',
        'create' => 'dashboard.shop.shipping-methods.create',
        'store' => 'dashboard.shop.shipping-methods.store',
        'edit' => 'dashboard.shop.shipping-methods.edit',
        'update' => 'dashboard.shop.shipping-methods.update',
        'destroy' => 'dashboard.shop.shipping-methods.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Shipping Methods',
            singular: 'Shipping Method',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'price', 'header' => 'Price', 'align' => 'right'],
                    ['name' => 'free_above', 'header' => 'Free Above', 'align' => 'right'],
                    ['name' => 'sort_order', 'header' => 'Sort'],
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
                        ['name' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                        ['name' => 'price', 'type' => 'number', 'label' => 'Price', 'required' => true],
                        ['name' => 'free_above', 'type' => 'number', 'label' => 'Free Above', 'help' => 'Orders at or above this amount ship free.'],
                        ['name' => 'estimated_days_min', 'type' => 'number', 'label' => 'Estimated Delivery Min (days)'],
                        ['name' => 'estimated_days_max', 'type' => 'number', 'label' => 'Estimated Delivery Max (days)'],
                        ['name' => 'sort_order', 'type' => 'number', 'label' => 'Sort Order', 'help' => 'Lower numbers sort first.'],
                        ['name' => 'is_active', 'type' => 'boolean', 'label' => 'Active'],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'shop.shipping.manage',
                'create' => 'shop.shipping.manage',
                'edit' => 'shop.shipping.manage',
                'delete' => 'shop.shipping.manage',
            ],
        );
    }
}