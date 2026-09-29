<?php

namespace Nreljed\ShopModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for product attributes (plan 6.1 wave-11
 * conversion on the kernel resource path from platform 0.1.70).
 *
 * The terms list is a `nested` field: a reorderable row list where the rows
 * carry the term rows the old grid edited inline (name + swatch value). The
 * owning controller diffs the rows against what it persisted - rows kept `id`
 * update, rows without one create, the rest delete - and derives sort_order
 * from array order, so the drag ordering is the persisted ordering.
 *
 * The `listTerms`/`reorderTerms`/`reorder` JSON endpoints stay for the
 * storefront filter and variation builders; the admin editing surface moves
 * here.
 */
final class AttributeResource
{
    public const KEY = 'shop.attributes';

    public const ROUTES = [
        'index' => 'dashboard.shop.attributes.index',
        'create' => 'dashboard.shop.attributes.create',
        'store' => 'dashboard.shop.attributes.store',
        'edit' => 'dashboard.shop.attributes.edit',
        'update' => 'dashboard.shop.attributes.update',
        'destroy' => 'dashboard.shop.attributes.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Attributes',
            singular: 'Attribute',
            table: [
                'columns' => [
                    ['name' => 'name', 'header' => 'Name', 'sortable' => true],
                    ['name' => 'display_type', 'header' => 'Display Type'],
                    ['name' => 'terms_count', 'header' => 'Terms', 'align' => 'right'],
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
                        ['name' => 'display_type', 'type' => 'select', 'label' => 'Display Type', 'options' => [
                            ['value' => 'text', 'label' => 'Text - badge chips with text labels'],
                            ['value' => 'color', 'label' => 'Color - circular color swatches (hex values)'],
                            ['value' => 'image', 'label' => 'Image - circular image swatches (photo upload)'],
                            ['value' => 'button', 'label' => 'Button - rectangle buttons with text'],
                        ]],
                        ['name' => 'is_active', 'type' => 'boolean', 'label' => 'Active'],
                    ],
                ],
                [
                    'title' => 'Terms',
                    'fields' => [
                        ['name' => 'terms', 'type' => 'nested', 'label' => 'Terms', 'colSpan' => 2, 'rowTitleField' => 'name', 'help' => 'One row per term, e.g. "Red" or "Small". Drag to reorder; the saved order is the display order.', 'rows' => [
                            ['name' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                            ['name' => 'swatch_value', 'type' => 'text', 'label' => 'Swatch Value', 'help' => 'Hex color (e.g. #ef4444) for color swatches or an image URL for image swatches.'],
                        ]],
                    ],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'shop.view',
                'create' => 'shop.products.create',
                'edit' => 'shop.products.edit',
                'delete' => 'shop.products.delete',
            ],
        );
    }
}