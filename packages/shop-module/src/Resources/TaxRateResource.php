<?php

namespace Nreljed\ShopModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for tax rates (plan 6.1 wave-1 proof of the
 * kernel resource path from platform 0.1.66).
 *
 * The table/form/route keys match `resources/js/lib/resource-schema.ts`
 * verbatim, and the six route names below MUST resolve to routes registered
 * by the shop module (the kernel's ResourceRenderer fails loud at render time
 * when one does not). Ownership stays here: the routes, controller and policy
 * remain the module's; the kernel only frames the rendered pages.
 */
final class TaxRateResource
{
    public const KEY = 'shop.tax-rates';

    public const ROUTES = [
        'index' => 'dashboard.shop.tax-rates.index',
        'create' => 'dashboard.shop.tax-rates.create',
        'store' => 'dashboard.shop.tax-rates.store',
        'edit' => 'dashboard.shop.tax-rates.edit',
        'update' => 'dashboard.shop.tax-rates.update',
        'destroy' => 'dashboard.shop.tax-rates.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Tax Rates',
            singular: 'Tax Rate',
            table: [
                'columns' => [
                    ['name' => 'id', 'header' => 'ID'],
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'country', 'header' => 'Country'],
                    ['name' => 'rate', 'header' => 'Rate', 'align' => 'right'],
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
                        ['name' => 'country', 'type' => 'text', 'label' => 'Country', 'help' => '2-letter ISO code, or leave blank for a fallback rate.'],
                        ['name' => 'state', 'type' => 'text', 'label' => 'State'],
                        ['name' => 'rate', 'type' => 'number', 'label' => 'Rate', 'help' => 'Percentage (e.g. 20.00) or a fixed amount.', 'required' => true],
                        ['name' => 'type', 'type' => 'select', 'label' => 'Type', 'options' => [
                            ['label' => 'Percentage', 'value' => 'percentage'],
                            ['label' => 'Fixed', 'value' => 'fixed'],
                        ]],
                        ['name' => 'is_active', 'type' => 'boolean', 'label' => 'Active'],
                        ['name' => 'is_default', 'type' => 'boolean', 'label' => 'Default'],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'shop.taxes.manage',
                'create' => 'shop.taxes.manage',
                'edit' => 'shop.taxes.manage',
                'delete' => 'shop.taxes.manage',
            ],
        );
    }
}