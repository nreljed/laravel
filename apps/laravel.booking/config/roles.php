<?php

return [

    'default_role' => 'customer',

    'permissions' => [

        ['name' => 'Manage Users', 'slug' => 'manage-users', 'description' => 'Create, edit, and delete users.', 'group' => 'Administration'],
        ['name' => 'Impersonate Users', 'slug' => 'impersonate-users', 'description' => 'Sign in as another user to troubleshoot.', 'group' => 'Administration'],
        ['name' => 'Assign Roles', 'slug' => 'assign-roles', 'description' => 'Assign and remove roles on users.', 'group' => 'Administration'],
        ['name' => 'Manage Settings', 'slug' => 'manage-settings', 'description' => 'Access and modify application settings.', 'group' => 'Administration'],
        ['name' => 'Manage Roles', 'slug' => 'manage-roles', 'description' => 'Create, edit, and delete roles.', 'group' => 'Administration'],
        ['name' => 'View System Health', 'slug' => 'view-system-health', 'description' => 'View system health and diagnostics.', 'group' => 'Administration'],
        ['name' => 'Manage Options', 'slug' => 'manage-options', 'description' => 'Manage system options and configurations.', 'group' => 'Administration'],
        ['name' => 'Manage Audit Logs', 'slug' => 'manage-audit-logs', 'description' => 'View system audit logs.', 'group' => 'Administration'],
        ['name' => 'Export Audit Logs', 'slug' => 'export-audit-logs', 'description' => 'Export audit log data as CSV.', 'group' => 'Administration'],
        ['name' => 'Manage Email Settings', 'slug' => 'manage-email-settings', 'description' => 'Configure email settings.', 'group' => 'Administration'],
        ['name' => 'View Dashboard', 'slug' => 'view-dashboard', 'description' => 'View the main dashboard.', 'group' => 'General'],
        ['name' => 'View Profile', 'slug' => 'view-profile', 'description' => 'View user profile.', 'group' => 'General'],
        ['name' => 'Edit Profile', 'slug' => 'edit-profile', 'description' => 'Edit user profile.', 'group' => 'General'],
        ['name' => 'Delete Account', 'slug' => 'delete-account', 'description' => 'Delete user account.', 'group' => 'General'],
        ['name' => 'View Notifications', 'slug' => 'view-notifications', 'description' => 'View notifications.', 'group' => 'General'],
        ['name' => 'Manage Notifications', 'slug' => 'manage-notifications', 'description' => 'Manage notification preferences.', 'group' => 'General'],
        ['name' => 'Manage Media', 'slug' => 'manage-media', 'description' => 'Upload and manage media files.', 'group' => 'General'],
        ['name' => 'View Audit Logs', 'slug' => 'view-audit-logs', 'description' => 'View audit logs.', 'group' => 'Administration'],
        ['name' => 'Manage Contacts', 'slug' => 'manage-contacts', 'description' => 'Manage contact form submissions.', 'group' => 'Administration'],
        ['name' => 'View Contacts', 'slug' => 'view-contacts', 'description' => 'View contact form submissions.', 'group' => 'Administration'],
        ['name' => 'Manage Pages', 'slug' => 'manage-pages', 'description' => 'Manage CMS pages.', 'group' => 'Administration'],
        ['name' => 'View Pages', 'slug' => 'view-pages', 'description' => 'View CMS pages.', 'group' => 'Administration'],
        ['name' => 'Manage Email Templates', 'slug' => 'manage-email-templates', 'description' => 'Manage email templates.', 'group' => 'Administration'],

        // Properties
        ['name' => 'View Properties', 'slug' => 'properties.view', 'description' => 'View property listings.', 'group' => 'Properties'],
        ['name' => 'Create Properties', 'slug' => 'properties.create', 'description' => 'Create new property listings.', 'group' => 'Properties'],
        ['name' => 'Edit Properties', 'slug' => 'properties.edit', 'description' => 'Edit existing property listings.', 'group' => 'Properties'],
        ['name' => 'Delete Properties', 'slug' => 'properties.delete', 'description' => 'Delete property listings.', 'group' => 'Properties'],

        // Bookings
        ['name' => 'View Bookings', 'slug' => 'bookings.view', 'description' => 'View booking records.', 'group' => 'Bookings'],
        ['name' => 'Create Bookings', 'slug' => 'bookings.create', 'description' => 'Create new bookings.', 'group' => 'Bookings'],
        ['name' => 'Edit Bookings', 'slug' => 'bookings.edit', 'description' => 'Edit existing bookings.', 'group' => 'Bookings'],
        ['name' => 'Delete Bookings', 'slug' => 'bookings.delete', 'description' => 'Delete booking records.', 'group' => 'Bookings'],

    ],

    'roles' => [

        'admin' => [
            'name' => 'Administrator',
            'description' => 'Full system access.',
            'permissions' => ['*'],
        ],

        'editor' => [
            'name' => 'Editor',
            'description' => 'Can manage content and users.',
            'permissions' => [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'manage-users',
                'view-notifications',
                'manage-notifications',
                'properties.view',
                'properties.create',
                'properties.edit',
                'properties.delete',
                'bookings.view',
                'bookings.create',
                'bookings.edit',
                'bookings.delete',
            ],
        ],

        'user' => [
            'name' => 'User',
            'description' => 'Basic user access.',
            'permissions' => [
                'view-dashboard',
                'view-profile',
                'edit-profile',
                'delete-account',
                'view-notifications',
            ],
        ],

        'customer' => [
            'name' => 'Customer',
            'description' => 'Account management: profile, notifications, account settings.',
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
