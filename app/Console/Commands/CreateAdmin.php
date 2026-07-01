<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
        {--email= : Admin email}
        {--password= : Admin password}
        {--name= : Admin display name}';

    protected $description = 'Create or update the first admin user';

    public function handle(): int
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('roles')) {
            $this->error('Tables do not exist. Run migrations first.');

            return Command::FAILURE;
        }

        $email = $this->option('email') ?: env('ADMIN_EMAIL', 'nstudio.webdesign@gmail.com');
        $password = $this->option('password') ?: env('ADMIN_PASSWORD');
        $name = $this->option('name') ?: env('ADMIN_NAME', 'Admin');

        $existing = User::where('email', $email)->first();
        if ($existing && ! $password) {
            $this->info("Admin {$email} already exists, skipping.");

            return Command::SUCCESS;
        }

        if (! $password) {
            $this->error('Password is required.');

            return Command::FAILURE;
        }

        $role = Role::where('slug', 'admin')->first();
        if (! $role) {
            $this->error('Admin role not found. Run php artisan db:seed --class=RolesAndPermissionsSeeder first.');

            return Command::FAILURE;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => str($name)->slug('_')->toString(),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $user->roles()->syncWithoutDetaching([$role->id]);

        if ($user->wasRecentlyCreated) {
            $this->info("Admin user created: {$email}");
        } else {
            $this->info("Admin user updated: {$email}");
        }

        return Command::SUCCESS;
    }
}
