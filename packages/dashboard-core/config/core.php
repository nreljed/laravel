<?php

use Nreljed\DashboardCore\Blog\BlogServiceProvider;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Http\Middleware\HandleInertiaRequests;
use Nreljed\DashboardCore\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Load Migrations
    |--------------------------------------------------------------------------
    |
    | Whether the package should load its own migrations. Disable this if the
    | consuming application already ships equivalent migrations for the same
    | tables (e.g. an app that migrated from a monolith where the core tables
    | were created by the application's own migration files).
    |
    */

    'load_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Inertia Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware class appended to the "web" group that shares the Inertia
    | page props. Consuming applications may override this with a subclass
    | that adds project-specific shared data.
    |
    */

    'inertia_middleware' => HandleInertiaRequests::class,

    /*
    |--------------------------------------------------------------------------
    | Feature Service Providers
    |--------------------------------------------------------------------------
    |
    | The self-contained feature providers discovered by the FeatureRegistry.
    | Each entry must be a registered service provider implementing the shared
    | feature contract (slug(), getNavItems(), getSharedData(), permissions(),
    | and the optional getDashboardData()/getAnalyticsSeries() hooks).
    |
    */

    'features' => [
        BlogServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Orphan Media Targets
    |--------------------------------------------------------------------------
    |
    | Folder => model class pairs scanned by the core:cleanup-orphan-media
    | command. Each model class must expose a getReferencedPaths() method that
    | returns the storage paths it references.
    |
    */

    'orphan_media' => [
        'posts' => Post::class,
        'avatars' => User::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings Groups
    |--------------------------------------------------------------------------
    |
    | Additional settings groups registered by consuming applications. The
    | base groups (general, registration, notifications, security, email,
    | maintenance, formatting, audit_logs) are always included. Feature
    | service providers may add domain-specific groups here.
    |
    */

    'settings' => [
        'groups' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Core Notification Types
    |--------------------------------------------------------------------------
    |
    | Notification types shipped by the core package itself (features register
    | their own types via getNotificationTypes()). Each type supports the
    | following keys: label, description, group, severity, default_channels,
    | default_enabled, and optional email_template.
    |
    */

    'notification_types' => [
        'database.maintenance' => [
            'label' => 'Database maintenance',
            'description' => 'Reset/reseed runs performed by an admin or the deploy endpoint.',
            'group' => 'System',
            'severity' => 'critical',
            'default_channels' => ['in_app'],
            'default_enabled' => true,
        ],
        'user.welcome' => [
            'label' => 'Welcome emails',
            'description' => 'Account-created emails sent to new users.',
            'group' => 'Account',
            'severity' => 'info',
            'default_channels' => ['mail'],
            'default_enabled' => true,
        ],
    ],

];
