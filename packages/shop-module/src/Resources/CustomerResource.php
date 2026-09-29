<?php

namespace Nreljed\ShopModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for the customer list (plan 6.1 wave-8 conversion
 * on the kernel resource path from platform 0.1.68).
 *
 * Customers are not an admin CRUD surface: there are no create / store / edit
 * routes, so the descriptor declares only the slot it renders through —
 * `index` — plus a serialized GET row action that drills into the module's
 * rich hand-rolled detail page (`dashboard.shop.customers.show`, which binds
 * the kernel's literal `record` param).
 *
 * The flat `orders_count` / `total_spent` columns are filled by the index
 * controller from subselects, because a PHP descriptor cannot carry a `cell`
 * closure that computes derived values.
 */
final class CustomerResource
{
    public const KEY = 'shop.customers';

    public const ROUTES = [
        'index' => 'dashboard.shop.customers.index',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Customers',
            singular: 'Customer',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'email', 'header' => 'Email'],
                    ['name' => 'orders_count', 'header' => 'Orders', 'align' => 'right'],
                    ['name' => 'total_spent', 'header' => 'Total Spent', 'align' => 'right'],
                    ['name' => 'created_at', 'header' => 'Joined'],
                ],
                'searchKeys' => ['name', 'email'],
                'searchPlaceholder' => 'Search customers by name or email',
                'rowActions' => [
                    ['label' => 'View profile', 'routeName' => 'dashboard.shop.customers.show', 'method' => 'get'],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'shop.orders.view',
            ],
        );
    }
}