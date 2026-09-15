<?php

use Nreljed\Platform\Http\Middleware\HandleInertiaRequests;

return [

    /*
    |--------------------------------------------------------------------------
    | Load Migrations
    |--------------------------------------------------------------------------
    |
    | Enable package migrations — the shop app does not ship its own
    | equivalents for the core tables.
    |
    */

    'load_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Inertia Request Middleware
    |--------------------------------------------------------------------------
    |
    | Shared props handler appended to the web group. Modules contribute
    | shared props declaratively via FeatureRegistry::getAllSharedData().
    |
    */

    'inertia_middleware' => HandleInertiaRequests::class,

    /*
    |--------------------------------------------------------------------------
    | Settings Groups
    |--------------------------------------------------------------------------
    |
    | Additional settings groups beyond the core defaults.
    |
    */

    'settings' => [
        'groups' => ['localization'],
    ],

];
