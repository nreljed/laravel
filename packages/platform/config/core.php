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
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | The CSP middleware sends the Content-Security-Policy header everywhere
    | except local development, where it is skipped unless this flag is on.
    | Set CSP_ENABLED=true to force the policy in local (or any) environment.
    |
    */

    'csp_enabled' => env('CSP_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Health Git Status Exclude
    |--------------------------------------------------------------------------
    |
    | Top-level path prefixes (relative to the app root) ignored when the
    | system health page derives the git "dirty" flag. In a monorepo/workspace
    | the consuming app is one member among many (e.g. packages/ and apps/),
    | so changes in sibling trees should not mark the app's build dirty.
    |
    */

    'health_git_status_exclude' => [],

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
