<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['slug' => 'properties', 'name' => 'Properties', 'description' => 'Manage property listings with pricing, surface area, and rich metadata.', 'enabled' => true],
            ['slug' => 'bookings', 'name' => 'Bookings', 'description' => 'Manage guest bookings, availability, and reservations.', 'enabled' => true],
            ['slug' => 'shop', 'name' => 'Shop', 'description' => 'Manage your online store: products, orders, inventory, and customers.', 'enabled' => true],
            ['slug' => 'posts', 'name' => 'Posts', 'description' => 'Create and manage blog posts and articles.', 'enabled' => true],
        ];

        foreach ($modules as $module) {
            Module::firstOrCreate(
                ['slug' => $module['slug']],
                [
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'enabled' => $module['enabled'],
                    'version' => '1.0.0',
                ]
            );
        }
    }
}
