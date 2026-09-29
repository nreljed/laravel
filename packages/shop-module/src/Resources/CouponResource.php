<?php

namespace Nreljed\ShopModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;
use Nreljed\ShopModule\Models\Coupon;

/**
 * Declarative admin resource for coupons (plan 6.1 wave-5 conversion on the
 * kernel resource path from platform 0.1.66).
 *
 * The product restriction set (`product_ids`) is exposed as the first lazy
 * `optionsUrl` relation field — the options catalog is fetched from the
 * module's `/products/options` endpoint rather than shipped statically,
 * because a product list is not a bounded enum. The store controller reads
 * and syncs the submitted ids against the coupon's `products` pivot.
 *
 * Still deliberately not exposed: the free-product picker (`free_product_id`)
 * and the tiered JSON (`tiers`) — tiers need the 5.3 nested-shape kit work
 * and would otherwise pretend to a form that cannot keep their invariants.
 * Like the other conversions, mutation endpoints stay in the module.
 */
final class CouponResource
{
    public const KEY = 'shop.coupons';

    public const ROUTES = [
        'index' => 'dashboard.shop.coupons.index',
        'create' => 'dashboard.shop.coupons.create',
        'store' => 'dashboard.shop.coupons.store',
        'edit' => 'dashboard.shop.coupons.edit',
        'update' => 'dashboard.shop.coupons.update',
        'destroy' => 'dashboard.shop.coupons.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Coupons',
            singular: 'Coupon',
            table: [
                'columns' => [
                    ['name' => 'code', 'header' => 'Code'],
                    ['name' => 'type', 'header' => 'Type'],
                    ['name' => 'value', 'header' => 'Value', 'align' => 'right'],
                    ['name' => 'used_count', 'header' => 'Used', 'align' => 'right'],
                    ['name' => 'is_active', 'header' => 'Active'],
                    ['name' => 'expires_at', 'header' => 'Expires'],
                    ['name' => 'created_at', 'header' => 'Created'],
                ],
                'searchKey' => 'code',
            ],
            form: [
                [
                    'title' => 'Discount',
                    'fields' => [
                        ['name' => 'code', 'type' => 'text', 'label' => 'Code', 'required' => true, 'help' => 'Uppercased on save; must be unique.'],
                        ['name' => 'type', 'type' => 'select', 'label' => 'Type', 'required' => true, 'placeholder' => 'Select a discount type', 'options' => array_map(static fn (string $type): array => ['value' => $type, 'label' => ucwords(str_replace('_', ' ', $type))], Coupon::TYPES)],
                        ['name' => 'value', 'type' => 'number', 'label' => 'Value', 'help' => 'Percentage or fixed amount; forced to 0 for free shipping.'],
                        ['name' => 'min_order_amount', 'type' => 'number', 'label' => 'Minimum Order'],
                        ['name' => 'max_discount', 'type' => 'number', 'label' => 'Maximum Discount'],
                    ],
                ],
                [
                    'title' => 'Restrictions',
                    'fields' => [
                        ['name' => 'product_ids', 'type' => 'relation', 'label' => 'Product Restrictions', 'colSpan' => 2, 'optionsUrl' => 'dashboard.shop.products.options', 'help' => 'Restrict this coupon to specific products; leave empty to apply to all products.'],
                    ],
                ],
                [
                    'title' => 'Limits & Validity',
                    'fields' => [
                        ['name' => 'usage_limit', 'type' => 'number', 'label' => 'Usage Limit', 'help' => 'Blank means unlimited.'],
                        ['name' => 'per_user_limit', 'type' => 'number', 'label' => 'Per-User Limit'],
                        ['name' => 'starts_at', 'type' => 'datetime', 'label' => 'Valid From'],
                        ['name' => 'expires_at', 'type' => 'datetime', 'label' => 'Expires At'],
                        ['name' => 'customer_group_id', 'type' => 'number', 'label' => 'Customer Group ID', 'help' => 'Leave blank to apply to all customers.'],
                    ],
                ],
                [
                    'title' => 'Behaviour',
                    'fields' => [
                        ['name' => 'is_active', 'type' => 'boolean', 'label' => 'Active'],
                        ['name' => 'is_automatic', 'type' => 'boolean', 'label' => 'Apply Automatically'],
                        ['name' => 'auto_discount_label', 'type' => 'text', 'label' => 'Automatic Discount Label'],
                        ['name' => 'exclude_sale_items', 'type' => 'boolean', 'label' => 'Exclude Sale Items'],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'shop.view',
                'create' => 'shop.coupons.manage',
                'edit' => 'shop.coupons.manage',
                'delete' => 'shop.coupons.manage',
            ],
        );
    }
}