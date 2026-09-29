<?php

namespace Nreljed\ShopModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Services\OrderStatusService;

/**
 * Declarative admin resource for the order list (plan 6.1 wave-9 conversion on
 * the kernel resource path from platform 0.1.68).
 *
 * Orders are a list-and-detail surface, not an admin CRUD one: the module's
 * hand-rolled create/form and show (actions, refunds, notes, invoice) pages
 * stay, so the descriptor declares the two slots the generic pages render —
 * `index` and `create` (the latter sends operators into the custom order
 * form) — plus a serialized GET row action that opens the rich detail page
 * (`dashboard.shop.orders.show`, bound to the kernel's literal `record` param).
 *
 * The `status` filter narrows the same array client-side; the flat `total` /
 * `currency` columns are filled by the index controller, because a PHP
 * descriptor cannot carry a `cell` closure that derives display values.
 */
final class OrderResource
{
    public const KEY = 'shop.orders';

    public const ROUTES = [
        'index' => 'dashboard.shop.orders.index',
        'create' => 'dashboard.shop.orders.create',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Orders',
            singular: 'Order',
            table: [
                'columns' => [
                    ['name' => 'order_number', 'header' => 'Order', 'sortable' => true],
                    ['name' => 'customer_name', 'header' => 'Customer'],
                    ['name' => 'customer_email', 'header' => 'Email'],
                    ['name' => 'status', 'header' => 'Status'],
                    ['name' => 'total', 'header' => 'Total', 'align' => 'right'],
                    ['name' => 'currency', 'header' => 'Currency'],
                    ['name' => 'payment_status', 'header' => 'Payment'],
                    ['name' => 'created_at', 'header' => 'Placed'],
                ],
                'searchKeys' => ['order_number', 'customer_name', 'customer_email'],
                'searchPlaceholder' => 'Search by order number, customer name or email',
                'filters' => [
                    [
                        'key' => 'status',
                        'title' => 'Status',
                        'options' => array_map(
                            static fn (string $status): array => [
                                'value' => $status,
                                'label' => ucwords(str_replace('_', ' ', $status)),
                            ],
                            OrderStatusService::STATUSES,
                        ),
                    ],
                ],
                'rowActions' => [
                    ['label' => 'View order', 'routeName' => 'dashboard.shop.orders.show', 'method' => 'get'],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'shop.orders.view',
                'create' => 'shop.orders.edit',
            ],
        );
    }
}