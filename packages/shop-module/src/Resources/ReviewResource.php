<?php

namespace Nreljed\ShopModule\Resources;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * Declarative admin resource for product reviews (plan 6.1 wave-6 conversion
 * on the kernel resource path from platform 0.1.67).
 *
 * Reviews are a moderation surface, not a CRUD one: the page lists pending and
 * approved reviews and acts per-row (approve / reject / delete). The kernel
 * generic index needs the six standard route names *as names* for the
 * renderer to accept the descriptor, but `store` is already owned by the
 * customer submission route (`dashboard.shop.reviews.store`, *POST
 * /products/{product}/reviews`), so this descriptor declares only the slots it
 * actually renders through — `index` and `destroy` — and the approve/reject
 * mutations come through serialized row actions. The generic pages hand only
 * what the module's routes wire.
 *
 * The flat `product_name` / `user_name` columns are filled by the index
 * controller from the loaded relations, because a PHP descriptor cannot carry
 * a `cell` closure that dereferences a nested product object.
 */
final class ReviewResource
{
    public const KEY = 'shop.reviews';

    public const ROUTES = [
        'index' => 'dashboard.shop.reviews.index',
        'destroy' => 'dashboard.shop.reviews.destroy',
    ];

    public static function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            key: self::KEY,
            label: 'Reviews',
            singular: 'Review',
            table: [
                'columns' => [
                    ['name' => 'product_name', 'header' => 'Product', 'sortable' => true],
                    ['name' => 'user_name', 'header' => 'Customer'],
                    ['name' => 'rating', 'header' => 'Rating', 'align' => 'center'],
                    ['name' => 'content', 'header' => 'Review'],
                    ['name' => 'is_approved', 'header' => 'Status'],
                    ['name' => 'created_at', 'header' => 'Created'],
                ],
                'searchKey' => 'content',
                'searchPlaceholder' => 'Search review text',
                'rowActions' => [
                    ['label' => 'Approve', 'routeName' => 'dashboard.shop.reviews.approve', 'method' => 'post', 'confirm' => 'Approve this review?'],
                    ['label' => 'Reject', 'routeName' => 'dashboard.shop.reviews.reject', 'method' => 'post', 'confirm' => 'Reject this review?'],
                ],
            ],
            routes: self::ROUTES,
            permissions: [
                'view' => 'shop.view',
                'delete' => 'shop.reviews.moderate',
            ],
        );
    }
}
