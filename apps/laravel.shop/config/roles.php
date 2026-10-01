<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Role
    |--------------------------------------------------------------------------
    */

    'default_role' => 'customer',

    /*
    |--------------------------------------------------------------------------
    | App-Specific Permission Catalog (delta)
    |--------------------------------------------------------------------------
    |
    | Only Shop-specific additions on top of the engine's base_permissions.
    | RoleCatalog merges base_permissions + this delta + every feature
    | provider's permissions(). To change a base entry, declare the same
    | slug here — the app entry wins.
    |
    */

    'permissions' => [
        // Legacy alias for manage-options (kept for backward-compat with
        // existing role assignments, permission checks and tests).
        ['name' => 'Manage Options', 'slug' => 'manage_options', 'description' => 'Feature-level settings and system operations (admin only) — legacy alias', 'group' => 'Administration'],
    ],

    /*
    |--------------------------------------------------------------------------
    | App Role Catalog (delta)
    |--------------------------------------------------------------------------
    |
    | Roles the Shop overrides (same slug as a base role wins) or adds on top
    | of base_roles. The base user/author/editor/admin roles are inherited
    | unless redeclared here.
    |
    */

    'roles' => [
        'user' => [
            'name' => 'User',
            'description' => 'Can manage own account and browse the store.',
            'sort_order' => 3,
            'permissions' => [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
                'manage-notifications',
                'shop.view',
                'posts.view',
                'posts.create',
                'posts.edit',
                'posts.delete',
            ],
        ],

        'author' => [
            'name' => 'Author',
            'description' => 'Can add content and manage own account. Limited shop access.',
            'sort_order' => 2,
            'permissions' => [
                '*',
                '!posts.manage',
                '!manage-users',
                '!impersonate-users',
                '!assign-roles',
                '!manage-settings',
                '!manage-roles',
                '!view-system-health',
                '!manage-options',
                '!manage_options',
                '!manage-audit-logs',
                '!export-audit-logs',
                '!manage-email-settings',
                // Legacy Posts term-management slugs retained as no-op
                // exclusions so orphaned rows granted via '*' never resurface.
                '!manage-categories',
                '!manage-tags',
                '!posts.categories.manage',
                '!posts.tags.manage',
                '!shop.orders.refund',
                '!shop.webhooks.manage',
                '!shop.currencies.manage',
            ],
        ],

        'editor' => [
            'name' => 'Editor',
            'description' => 'Every permission except the admin panel. Full shop and content management.',
            'sort_order' => 1,
            'permissions' => [
                '*',
                '!manage-users',
                '!impersonate-users',
                '!assign-roles',
                '!manage-settings',
                '!manage-roles',
                '!view-system-health',
                '!manage-options',
                '!manage_options',
                '!manage-audit-logs',
                '!export-audit-logs',
                '!manage-email-settings',
            ],
        ],

        'shop_owner' => [
            'name' => 'Shop Owner',
            'description' => 'Full shop control: products, orders, categories, reviews, etc.',
            'sort_order' => 2,
            'permissions' => [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
                'manage-notifications',
                'shop.view',
                'shop.manage',
                'shop.products.create',
                'shop.products.edit',
                'shop.products.delete',
                'shop.orders.view',
                'shop.orders.edit',
                'shop.orders.refund',
                'shop.tags.manage',
                'shop.brands.manage',
                'shop.coupons.manage',
                'shop.reviews.moderate',
                'shop.shipping.manage',
                'shop.shipments.manage',
                'shop.taxes.manage',
                'shop.webhooks.manage',
                'shop.currencies.manage',
                'posts.view',
                'posts.create',
                'posts.edit',
                'posts.delete',
                'posts.categories.manage',
                'posts.tags.manage',
            ],
        ],

        'shop_manager' => [
            'name' => 'Shop Manager',
            'description' => 'Manage existing shop content: edit products, view orders',
            'sort_order' => 3,
            'permissions' => [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
                'manage-notifications',
                'shop.view',
                'shop.manage',
                'shop.products.edit',
                'shop.orders.view',
                'shop.orders.edit',
                'posts.view',
                'posts.edit',
            ],
        ],

        'customer' => [
            'name' => 'Customer',
            'description' => 'Account management: profile, notifications, account settings',
            'sort_order' => 4,
            'permissions' => [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
                'manage-notifications',
            ],
        ],
    ],

];
