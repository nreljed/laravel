<?php

use Database\Seeders\BlogContentSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

/*
|--------------------------------------------------------------------------
| Database Maintenance
|--------------------------------------------------------------------------
|
| Controls whether the destructive database reset/reseed UI is available.
| Defaults to enabled everywhere except production. Production is locked down
| to the token-protected /deploy/reset endpoint (triggered by the
| database-reset GitHub Action); set DB_MAINTENANCE_ENABLED=true only if you
| explicitly want the UI available there too.
|
*/

return [

    'enabled' => env('DB_MAINTENANCE_ENABLED', ! app()->environment('production')),

    /*
    |--------------------------------------------------------------------------
    | Seeder Groups
    |--------------------------------------------------------------------------
    |
    | Catalog of seeders exposed to the admin UI (reseed) and used by reset.
    | `baseline` groups are always re-run on reset (roles/permissions + settings
    | only — production stays clean). Non-baseline groups are demo content and
    | are only re-run on reset when explicitly requested (`with_demo`).
    |
    | Keys must be stable slugs — they are stored in audit logs and sent by the
    | frontend/GitHub Action. Consuming apps override this array in their own
    | published config to point at their seeders.
    |
    */

    'seeders' => [
        'roles' => [
            'class' => RolesAndPermissionsSeeder::class,
            'label' => 'Roles & Permissions',
            'destructive' => false,
            'baseline' => true,
        ],
        'settings' => [
            'class' => SettingsSeeder::class,
            'label' => 'Settings',
            'destructive' => false,
            'baseline' => true,
        ],
        'demo_users' => [
            'class' => DemoUsersSeeder::class,
            'label' => 'Demo users',
            'destructive' => false,
            'baseline' => false,
        ],
        'blog' => [
            'class' => BlogContentSeeder::class,
            'label' => 'Blog content',
            'destructive' => true,
            'baseline' => false,
        ],
    ],
];
