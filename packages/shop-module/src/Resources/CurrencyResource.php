<?php

namespace Nreljed\ShopModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for currencies (plan 6.1 wave-2 conversion on
 * the kernel resource path from platform 0.1.66).
 *
 * The route names must keep matching the routes registered by the shop module
 * (the kernel's ResourceRenderer fails loud at render time otherwise); edit/
 * update/destroy bind the literal record param. CRUD business rules — the
 * single-default invariant and the CurrencyService cache clear — stay in the
 * module's controller.
 */
final class CurrencyResource
{
    public const KEY = 'shop.currencies';

    public const ROUTES = [
        'index' => 'dashboard.shop.currencies.index',
        'create' => 'dashboard.shop.currencies.create',
        'store' => 'dashboard.shop.currencies.store',
        'edit' => 'dashboard.shop.currencies.edit',
        'update' => 'dashboard.shop.currencies.update',
        'destroy' => 'dashboard.shop.currencies.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Currencies',
            singular: 'Currency',
            table: [
                'columns' => [
                    ['name' => 'code', 'header' => 'Code'],
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'symbol', 'header' => 'Symbol'],
                    ['name' => 'exchange_rate', 'header' => 'Rate', 'align' => 'right'],
                    ['name' => 'is_default', 'header' => 'Default'],
                    ['name' => 'is_active', 'header' => 'Active'],
                    ['name' => 'created_at', 'header' => 'Created'],
                ],
                'searchKey' => 'name',
            ],
            form: [
                [
                    'title' => 'Details',
                    'fields' => [
                        ['name' => 'code', 'type' => 'text', 'label' => 'Code', 'placeholder' => 'USD', 'required' => true, 'help' => '3-letter ISO 4217 code.'],
                        ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                        ['name' => 'symbol', 'type' => 'text', 'label' => 'Symbol', 'placeholder' => '$', 'required' => true],
                        ['name' => 'exchange_rate', 'type' => 'number', 'label' => 'Exchange Rate', 'required' => true, 'help' => 'Units of this currency per 1 store base currency.'],
                        ['name' => 'decimal_places', 'type' => 'number', 'label' => 'Decimal Places', 'required' => true, 'help' => '0–4 digits after the decimal point.'],
                        ['name' => 'is_default', 'type' => 'boolean', 'label' => 'Default'],
                        ['name' => 'is_active', 'type' => 'boolean', 'label' => 'Active'],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'shop.currencies.manage',
                'create' => 'shop.currencies.manage',
                'edit' => 'shop.currencies.manage',
                'delete' => 'shop.currencies.manage',
            ],
        );
    }
}