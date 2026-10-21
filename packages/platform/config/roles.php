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
    | Base Permission Catalog
    |--------------------------------------------------------------------------
    |
    | The engine-provided permission catalog shared by every application that
    | consumes the dashboard core. Do not edit these in your own config/roles.php
    | -- list only your app-specific additions under "permissions" below. The
    | RoleCatalog helper merges base_permissions + permissions + every feature
    | provider's permissions() automatically, so the seeder and the permission
    | UI can never drift from the engine.
    |
    | Each entry carries an optional "group" key used by the UI to bucket
    | permissions (General, Posts, Administration). Entries without a group fall
    | back to "Administration". Feature providers may set "group" on their own
    | permissions; if absent it defaults to the provider's name().
    |
    */

    'base_permissions' => [
        ['name' => 'View Dashboard', 'slug' => 'view-dashboard', 'description' => 'Access the main dashboard', 'group' => 'General'],
        ['name' => 'View Profile', 'slug' => 'view-profile', 'description' => 'View own profile', 'group' => 'General'],
        ['name' => 'Edit Profile', 'slug' => 'edit-profile', 'description' => 'Edit own name, email, password', 'group' => 'General'],
        ['name' => 'Delete Account', 'slug' => 'delete-account', 'description' => 'Delete own account', 'group' => 'General'],
        ['name' => 'View Notifications', 'slug' => 'view-notifications', 'description' => 'View notifications', 'group' => 'General'],
        ['name' => 'Manage Notifications', 'slug' => 'manage-notifications', 'description' => 'Mark read, delete notifications', 'group' => 'General'],
        ['name' => 'Manage Users', 'slug' => 'manage-users', 'description' => 'Create, edit, delete users', 'group' => 'Administration'],
        ['name' => 'Impersonate Users', 'slug' => 'impersonate-users', 'description' => 'Sign in as another user to troubleshoot', 'group' => 'Administration'],
        ['name' => 'Assign Roles', 'slug' => 'assign-roles', 'description' => 'Assign and remove roles on users', 'group' => 'Administration'],
        ['name' => 'Manage Settings', 'slug' => 'manage-settings', 'description' => 'Change application settings', 'group' => 'Administration'],
        ['name' => 'Manage Roles', 'slug' => 'manage-roles', 'description' => 'Create, edit, delete roles and permissions', 'group' => 'Administration'],
        ['name' => 'View System Health', 'slug' => 'view-system-health', 'description' => 'View system diagnostics', 'group' => 'Administration'],
        ['name' => 'Manage Options', 'slug' => 'manage-options', 'description' => 'Feature-level settings and system operations (admin only)', 'group' => 'Administration'],
        ['name' => 'Manage Audit Logs', 'slug' => 'manage-audit-logs', 'description' => 'View system audit logs', 'group' => 'Administration'],
        ['name' => 'Manage Media', 'slug' => 'media.manage', 'description' => 'View and delete media uploaded by any user', 'group' => 'Administration'],
        ['name' => 'Export Audit Logs', 'slug' => 'export-audit-logs', 'description' => 'Export audit log data as CSV', 'group' => 'Administration'],
        ['name' => 'Manage Email Settings', 'slug' => 'manage-email-settings', 'description' => 'Configure email settings', 'group' => 'Administration'],
        ['name' => 'Manage Contact Messages', 'slug' => 'manage-contact', 'description' => 'Read, triage and delete public contact form submissions', 'group' => 'Administration'],
        ['name' => 'Manage Form Submissions', 'slug' => 'manage-submissions', 'description' => 'Read, triage, export and delete submissions from every public form', 'group' => 'Administration'],
    ],

    /*
    |--------------------------------------------------------------------------
    | App-Specific Permission Catalog (delta)
    |--------------------------------------------------------------------------
    |
    | List ONLY the additional permission entries your application introduces.
    | The engine's base_permissions above are merged in automatically by
    | RoleCatalog::permissions(). To *change* a base permission entry, declare
    | the same slug here -- your entry wins.
    |
    */

    'permissions' => [],

    /*
    |--------------------------------------------------------------------------
    | Base Role Catalog
    |--------------------------------------------------------------------------
    |
    | The engine-provided default roles. The seeder merges these with the app
    | catalog ("roles") using array_replace, so every consumer starts from the
    | same baseline and adds or overrides roles in its own config/roles.php.
    |
    | Each role maps to the permission slugs it grants. Use '*' to grant every
    | registered permission (admin). A role may combine '*' with '!' prefixed
    | exclusions to express "everything except ..." (e.g. editor = every
    | permission except admin-only ones). This keeps roles future-proof:
    | permissions added later are picked up automatically unless excluded.
    |
    */

    'base_roles' => [
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
                '!manage-submissions',
                // Legacy Blog term-management slugs retained as no-op
                // exclusions so orphaned rows granted via '*' never resurface.
                '!manage-categories',
                '!manage-tags',
                '!posts.categories.manage',
                '!posts.tags.manage',
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
                '!manage-submissions',
            ],
        ],

        'admin' => [
            'name' => 'Admin',
            'description' => 'Full access to everything including admin panel',
            'sort_order' => 0,
            'permissions' => '*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | App Role Catalog (delta)
    |--------------------------------------------------------------------------
    |
    | Roles your application adds or overrides on top of base_roles. The seeder
    | merges base + this catalog via RoleCatalog::roles() (array_replace), so:
    |
    |   - A role slug listed here replaces the base role of the same slug.
    |   - Roles only present in base_roles are inherited unchanged.
    |   - New slugs are simply added.
    |
    | This means a consuming app normally only declares its deltas -- it no
    | longer needs to re-declare the engine's default roles.
    |
    */

    'roles' => [],

];
