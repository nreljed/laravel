<?php

use Nreljed\Platform\Http\Middleware\HandleInertiaRequests;

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
    | Dashboard Core GitHub Repo
    |--------------------------------------------------------------------------
    |
    | GitHub repository that hosts the platform distribution source
    | (https://github.com/nreljed/platform). Used by the system health
    | page to link recorded core commits and to check the latest core release.
    |
    */

    'github_repo' => env('CORE_GITHUB_REPO', 'nreljed/platform'),

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

];
