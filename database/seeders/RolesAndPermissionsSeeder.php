<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder as CoreRolesAndPermissionsSeeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CoreRolesAndPermissionsSeeder::class);
    }
}
