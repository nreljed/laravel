<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Nreljed\Platform\Database\Seeders\SettingsSeeder;
use Nreljed\Platform\Models\Role;
use Nreljed\ShopModule\Database\Seeders\CurrencySeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $password = Hash::make('azerty123/*');

        // Admin — full access
        User::factory()->create([
            'name' => 'nstudio',
            'email' => 'nstudio.webdesign@gmail.com',
            'username' => 'nstudio',
            'password' => $password,
        ])->roles()->attach(Role::where('slug', 'admin')->first()->id);

        // Shop Owner — full shop + posts control
        User::factory()->create([
            'name' => 'shop_owner',
            'email' => 'shopowner@example.com',
            'username' => 'shop_owner',
            'password' => $password,
        ])->roles()->attach(Role::where('slug', 'shop_owner')->first()->id);

        // Shop Manager — view/edit existing content
        User::factory()->create([
            'name' => 'shop_manager',
            'email' => 'shopmanager@example.com',
            'username' => 'shop_manager',
            'password' => $password,
        ])->roles()->attach(Role::where('slug', 'shop_manager')->first()->id);

        // Customer — account only (default registration role)
        User::factory()->create([
            'name' => 'customer',
            'email' => 'customer@example.com',
            'username' => 'customer',
            'password' => $password,
        ])->roles()->attach(Role::where('slug', 'customer')->first()->id);

        $this->call(SettingsSeeder::class);
        $this->call(CurrencySeeder::class);
    }
}
