<?php

use Database\Seeders\BlogContentSeeder;
use Database\Seeders\DemoUsersSeeder;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Database\Seeders\SettingsSeeder;

/*
|--------------------------------------------------------------------------
| Database Maintenance
|--------------------------------------------------------------------------
|
| Overrides the platform seeder catalog for this application.
| Baseline groups (roles/permissions + settings) are always re-run on reset
| and are owned by the platform package. Non-baseline groups are demo content
| owned by this app and are only re-run on reset when explicitly requested
| via the admin UI (`with_demo`, non-production) or the deploy endpoint.
|
| Keys must be stable slugs — they are stored in audit logs and sent by the
| frontend/GitHub Action. Do not rename them after deployment.
|
*/

return [

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
        'demo_users' => [
            'class' => DemoUsersSeeder::class,
            'label' => 'Demo users',
            'description' => 'Re-creates the demo user accounts. May overwrite existing demo content.',
            'destructive' => false,
            'baseline' => false,
        ],
        'blog' => [
            'class' => BlogContentSeeder::class,
            'label' => 'Blog content',
            'description' => 'Re-creates the demo blog posts, categories and tags. May overwrite existing demo content.',
            'destructive' => true,
            'baseline' => false,
        ],
    ],
];
