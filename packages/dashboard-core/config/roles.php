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
    | Each entry carries an optional "group" key used by the UI to bucket
    | permissions (General, Blog, Administration). Entries without a group fall
    | back to "Administration". Feature providers may set "group" on their own
    | permissions; if absent it defaults to the provider's name().
    |
    */

    'permissions' => [
        ['name' => 'View Dashboard', 'slug' => 'view-dashboard', 'description' => 'Access the main dashboard', 'group' => 'General'],
        ['name' => 'View Profile', 'slug' => 'view-profile', 'description' => 'View own profile', 'group' => 'General'],
        ['name' => 'Edit Profile', 'slug' => 'edit-profile', 'description' => 'Edit own name, email, password', 'group' => 'General'],
        ['name' => 'Delete Account', 'slug' => 'delete-account', 'description' => 'Delete own account', 'group' => 'General'],
        ['name' => 'View Notifications', 'slug' => 'view-notifications', 'description' => 'View notifications', 'group' => 'General'],
        ['name' => 'Manage Notifications', 'slug' => 'manage-notifications', 'description' => 'Mark read, delete notifications', 'group' => 'General'],
        ['name' => 'Manage Categories', 'slug' => 'manage-categories', 'description' => 'Manage post categories', 'group' => 'Blog'],
        ['name' => 'Manage Tags', 'slug' => 'manage-tags', 'description' => 'Manage post tags', 'group' => 'Blog'],
        ['name' => 'Manage Users', 'slug' => 'manage-users', 'description' => 'Create, edit, delete users', 'group' => 'Administration'],
        ['name' => 'Impersonate Users', 'slug' => 'impersonate-users', 'description' => 'Sign in as another user to troubleshoot', 'group' => 'Administration'],
        ['name' => 'Assign Roles', 'slug' => 'assign-roles', 'description' => 'Assign and remove roles on users', 'group' => 'Administration'],
        ['name' => 'Manage Settings', 'slug' => 'manage-settings', 'description' => 'Change application settings', 'group' => 'Administration'],
        ['name' => 'Manage Roles', 'slug' => 'manage-roles', 'description' => 'Create, edit, delete roles and permissions', 'group' => 'Administration'],
        ['name' => 'View System Health', 'slug' => 'view-system-health', 'description' => 'View system diagnostics', 'group' => 'Administration'],
        ['name' => 'Manage Options', 'slug' => 'manage-options', 'description' => 'Feature-level settings and system operations (admin only)', 'group' => 'Administration'],
        ['name' => 'Manage Audit Logs', 'slug' => 'manage-audit-logs', 'description' => 'View system audit logs', 'group' => 'Administration'],
        ['name' => 'Export Audit Logs', 'slug' => 'export-audit-logs', 'description' => 'Export audit log data as CSV', 'group' => 'Administration'],
        ['name' => 'Manage Email Settings', 'slug' => 'manage-email-settings', 'description' => 'Configure email settings', 'group' => 'Administration'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Catalog
    |--------------------------------------------------------------------------
    |
    | Roles = config catalog, per-app. Each role maps to the permission slugs it
    | grants. Use '*' to grant every registered permission (admin).
    |
    | A role may combine '*' with '!' prefixed exclusions to express "everything
    | except ..." (e.g. editor = every permission except admin-only ones). This
    | keeps roles future-proof: permissions added later are picked up
    | automatically unless explicitly excluded.
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
            'description' => 'Can manage own account and profile, and create posts scoped to own content.',
            'sort_order' => 3,
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
            ],
        ],

        'author' => [
            'name' => 'Author',
            'description' => 'Can add content and manage own account. Posts are scoped to own content; may select existing categories and tags but cannot create, edit, or delete them.',
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
                '!manage-audit-logs',
                '!export-audit-logs',
                '!manage-email-settings',
                '!manage-categories',
                '!manage-tags',
            ],
        ],

        'editor' => [
            'name' => 'Editor',
            'description' => 'Every permission except the admin panel (users, settings, roles, system health, audit logs, email settings). Can manage all posts.',
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
                '!manage-audit-logs',
                '!export-audit-logs',
                '!manage-email-settings',
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
