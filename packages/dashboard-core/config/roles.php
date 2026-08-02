<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Role
    |--------------------------------------------------------------------------
    |
    | The role assigned to newly registered users and used as the fallback when
    | no "default_role" setting exists in the settings table. Each consuming
    | application may override this value via its own config/roles.php.
    |
    */

    'default_role' => 'editor',

    /*
    |--------------------------------------------------------------------------
    | Core Permission Catalog
    |--------------------------------------------------------------------------
    |
    | Shared permission catalog for every application consuming the dashboard
    | core. Feature-level permissions (e.g. "posts.view") are declared by each
    | feature provider's permissions() method and are merged automatically by
    | the seeder -- do not list feature permissions here.
    |
    */

    'permissions' => [
        ['name' => 'Manage Users', 'slug' => 'manage-users', 'description' => 'Create, edit, delete users'],
        ['name' => 'Manage Settings', 'slug' => 'manage-settings', 'description' => 'Change application settings'],
        ['name' => 'Manage Roles', 'slug' => 'manage-roles', 'description' => 'Assign and manage roles'],
        ['name' => 'View System Health', 'slug' => 'view-system-health', 'description' => 'View system diagnostics'],
        ['name' => 'Manage Options', 'slug' => 'manage-options', 'description' => 'Feature-level settings and system operations (admin only)'],
        ['name' => 'Manage Audit Logs', 'slug' => 'manage-audit-logs', 'description' => 'View system audit logs'],
        ['name' => 'View Dashboard', 'slug' => 'view-dashboard', 'description' => 'Access the main dashboard'],
        ['name' => 'View Profile', 'slug' => 'view-profile', 'description' => 'View own profile'],
        ['name' => 'Edit Profile', 'slug' => 'edit-profile', 'description' => 'Edit own name, email, password'],
        ['name' => 'Delete Account', 'slug' => 'delete-account', 'description' => 'Delete own account'],
        ['name' => 'View Notifications', 'slug' => 'view-notifications', 'description' => 'View notifications'],
        ['name' => 'Manage Notifications', 'slug' => 'manage-notifications', 'description' => 'Mark read, delete notifications'],
        ['name' => 'Manage Categories', 'slug' => 'manage-categories', 'description' => 'Manage post categories'],
        ['name' => 'Manage Tags', 'slug' => 'manage-tags', 'description' => 'Manage post tags'],
        ['name' => 'Manage Email Settings', 'slug' => 'manage-email-settings', 'description' => 'Configure email settings'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Catalog
    |--------------------------------------------------------------------------
    |
    | Roles = config catalog, per-app. Each role maps to the permission slugs it
    | grants. Use '*' to grant every registered permission (admin).
    |
    | Consuming applications publish this file (php artisan vendor:publish
    | --tag=core-config) and override the "roles" array wholesale to define
    | their own catalog. Permission slugs reference the shared catalog above
    | plus any feature-declared permissions; slugs for features that are not
    | registered in the consuming app are silently ignored.
    |
    */

    'roles' => [
        'user' => [
            'name' => 'User',
            'description' => 'Can manage own account and profile',
            'sort_order' => 3,
            'permissions' => [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
                'manage-notifications',
            ],
        ],

        'author' => [
            'name' => 'Author',
            'description' => 'Can add content and manage own account',
            'sort_order' => 2,
            'permissions' => [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
                'manage-notifications',
                'posts.view',
                'posts.create',
                'posts.edit',
            ],
        ],

        'editor' => [
            'name' => 'Editor',
            'description' => 'Can manage the application without admin privileges',
            'sort_order' => 1,
            'permissions' => [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
                'manage-notifications',
                'posts.view',
                'posts.create',
                'posts.edit',
                'posts.delete',
                'manage-categories',
                'manage-tags',
                'manage-email-settings',
                'test-posts.view',
                'test-posts.create',
                'test-posts.edit',
                'test-posts.delete',
            ],
        ],

        'admin' => [
            'name' => 'Admin',
            'description' => 'Full access to everything including admin panel',
            'sort_order' => 0,
            'permissions' => '*',
        ],
    ],

];
