<?php

use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ShopProductSeeder;
use Nreljed\Platform\Database\Seeders\SettingsSeeder;
use Nreljed\ShopModule\Database\Seeders\CurrencySeeder;

/*
|--------------------------------------------------------------------------
| Database Maintenance
|--------------------------------------------------------------------------
|
| Overrides the platform seeder catalog for this shop application.
| Baseline groups always run on reset (roles/permissions, settings and the
| currencies the shop needs to function). Non-baseline groups are demo
| content and are only re-run on reset when explicitly requested via the
| admin UI (`with_demo`, non-production) or the deploy endpoint.
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
            'description' => 'Restores the default site settings (general, registration, security, email, shop defaults, ...). Safe to re-run.',
            'destructive' => false,
            'baseline' => true,
        ],
        'currencies' => [
            'class' => CurrencySeeder::class,
            'label' => 'Currencies',
            'description' => 'Re-creates the shop currencies (EUR, USD, GBP, TND). Safe to re-run.',
            'destructive' => false,
            'baseline' => true,
        ],
        'products' => [
            'class' => ShopProductSeeder::class,
            'label' => 'Shop products',
            'description' => 'Re-creates the demo shop catalog: brands, categories, tags, attributes, variations, downloads and reviews. May overwrite existing demo content.',
            'destructive' => true,
            'baseline' => false,
        ],
        'demo_data' => [
            'class' => DemoDataSeeder::class,
            'label' => 'Demo data (orders & customers)',
            'description' => 'Re-creates the full shop demo environment: customers, orders, coupons, reviews, stock history and blog content. May overwrite existing demo content.',
            'destructive' => true,
            'baseline' => false,
        ],
    ],
];
