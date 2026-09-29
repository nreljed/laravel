<?php

namespace Nreljed\ShopModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for shipping zones (plan 6.1 wave-10 conversion
 * on the kernel resource path from platform 0.1.70).
 *
 * Zones are a nested-shape surface: a zone is a country/region list plus a
 * pivot of shipping methods carrying per-method override prices. Both edit
 * through the kernel's `relation` (the bounded country catalog) and `nested`
 * (the method rows: lazy `select` over the method catalog + a number
 * override) fields - the kit whose nested rows landed in platform 0.1.70.
 *
 * Route slots pair with `ShippingZonesController`; mutation stays module-side
 * (the controller syncs the methods pivot from the nested rows).
 */
final class ShippingZoneResource
{
    public const KEY = 'shop.shipping-zones';

    public const ROUTES = [
        'index' => 'dashboard.shop.shipping-zones.index',
        'create' => 'dashboard.shop.shipping-zones.create',
        'store' => 'dashboard.shop.shipping-zones.store',
        'edit' => 'dashboard.shop.shipping-zones.edit',
        'update' => 'dashboard.shop.shipping-zones.update',
        'destroy' => 'dashboard.shop.shipping-zones.destroy',
    ];

    /**
     * Same bounded catalog the pre-conversion form shipped; codes only, so
     * the relation field stores the raw scalar values the zone model keeps.
     */
    public const COUNTRIES = [
        ['label' => 'Germany (DE)', 'value' => 'DE'],
        ['label' => 'France (FR)', 'value' => 'FR'],
        ['label' => 'Italy (IT)', 'value' => 'IT'],
        ['label' => 'Spain (ES)', 'value' => 'ES'],
        ['label' => 'Portugal (PT)', 'value' => 'PT'],
        ['label' => 'Netherlands (NL)', 'value' => 'NL'],
        ['label' => 'Belgium (BE)', 'value' => 'BE'],
        ['label' => 'Austria (AT)', 'value' => 'AT'],
        ['label' => 'Switzerland (CH)', 'value' => 'CH'],
        ['label' => 'United Kingdom (GB)', 'value' => 'GB'],
        ['label' => 'Ireland (IE)', 'value' => 'IE'],
        ['label' => 'Denmark (DK)', 'value' => 'DK'],
        ['label' => 'Sweden (SE)', 'value' => 'SE'],
        ['label' => 'Norway (NO)', 'value' => 'NO'],
        ['label' => 'Finland (FI)', 'value' => 'FI'],
        ['label' => 'Poland (PL)', 'value' => 'PL'],
        ['label' => 'Czech Republic (CZ)', 'value' => 'CZ'],
        ['label' => 'Slovakia (SK)', 'value' => 'SK'],
        ['label' => 'Hungary (HU)', 'value' => 'HU'],
        ['label' => 'Romania (RO)', 'value' => 'RO'],
        ['label' => 'Bulgaria (BG)', 'value' => 'BG'],
        ['label' => 'Greece (GR)', 'value' => 'GR'],
        ['label' => 'Croatia (HR)', 'value' => 'HR'],
        ['label' => 'United States (US)', 'value' => 'US'],
        ['label' => 'Canada (CA)', 'value' => 'CA'],
        ['label' => 'Australia (AU)', 'value' => 'AU'],
        ['label' => 'Japan (JP)', 'value' => 'JP'],
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Shipping Zones',
            singular: 'Shipping Zone',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'countries_count', 'header' => 'Countries', 'align' => 'right'],
                    ['name' => 'methods_count', 'header' => 'Methods', 'align' => 'right'],
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
                        ['name' => 'countries', 'type' => 'relation', 'label' => 'Countries', 'colSpan' => 2, 'options' => self::COUNTRIES, 'help' => 'No country selected means the zone applies to all countries.'],
                        ['name' => 'is_active', 'type' => 'boolean', 'label' => 'Active'],
                    ],
                ],
                [
                    'title' => 'Shipping Methods',
                    'fields' => [
                        ['name' => 'methods', 'type' => 'nested', 'label' => 'Methods', 'colSpan' => 2, 'help' => 'One row per method in this zone; leave the override blank to use the method\'s default price.', 'rows' => [
                            ['name' => 'id', 'type' => 'select', 'label' => 'Method', 'required' => true, 'optionsUrl' => 'dashboard.shop.shipping-methods.options'],
                            ['name' => 'override_price', 'type' => 'number', 'label' => 'Override Price', 'help' => 'Leave blank for the default price.'],
                        ]],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'shop.view',
                'create' => 'shop.shipping.manage',
                'edit' => 'shop.shipping.manage',
                'delete' => 'shop.shipping.manage',
            ],
        );
    }
}