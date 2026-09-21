<?php

use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Database\Seeders\SettingsSeeder;

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

    'enabled' => env('DB_MAINTENANCE_ENABLED', true),

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
    | This package ships only its own owning seeders. Demo-tier groups are
    | application content (demo users, blog posts, products, ...) — consuming
    | apps override this array in their published config to point at their
    | seeders, exactly like the baseline groups here.
    |
    | Keys must be stable slugs — they are stored in audit logs and sent by the
    | frontend/GitHub Action. Do not rename them after deployment.
    |
    */

    'seeders' => [
        'roles' => [
            'class' => RolesAndPermissionsSeeder::class,
            'label' => 'Roles & Permissions',
            'description' => 'Re-creates all core roles and their permission assignments. Safe to re-run.',
            'destructive' => false,
            'baseline' => true,
        ],
        'settings' => [
            'class' => SettingsSeeder::class,
            'label' => 'Settings',
            'description' => 'Restores the default site settings (general, registration, security, email, formatting, ...). Safe to re-run.',
            'destructive' => false,
            'baseline' => true,
        ],
    ],
];
