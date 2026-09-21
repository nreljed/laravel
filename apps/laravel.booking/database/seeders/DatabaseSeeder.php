<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Nreljed\Platform\Database\Seeders\RolesAndPermissionsSeeder;
use Nreljed\Platform\Database\Seeders\SettingsSeeder;
use Nreljed\Platform\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $password = Hash::make('azerty123/*');

        // Admin — full access
        User::factory()->create([
            'name' => 'nstudio webdesign',
            'email' => 'nstudio.webdesign@gmail.com',
            'username' => 'nstudio',
            'password' => $password,
        ])->roles()->attach(Role::where('slug', 'admin')->first()->id);

        // Customer — account only (default registration role)
        User::factory()->create([
            'name' => 'customer',
            'email' => 'customer@example.com',
            'username' => 'customer',
            'password' => $password,
        ])->roles()->attach(Role::where('slug', 'customer')->first()->id);

        $this->call(SettingsSeeder::class);
    }
}
