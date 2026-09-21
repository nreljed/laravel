<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Database\Seeders\SettingsSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(DemoUsersSeeder::class);
        $this->call(BlogContentSeeder::class);
        $this->call(SettingsSeeder::class);
    }
}
