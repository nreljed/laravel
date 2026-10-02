<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$key = $_GET['key'] ?? '';
$expected = $app['config']['app.deploy_token'] ?? '';
if (! $expected || ! hash_equals($expected, $key)) {
    http_response_code(403);
    exit('Invalid key');
}

header('Content-Type: text/plain');

try {
    $app->make(RolesAndPermissionsSeeder::class)->run();
    echo "RolesAndPermissionsSeeder: OK\n";

    $app->make(SettingsSeeder::class)->run();
    echo "SettingsSeeder: OK\n";

    $email = (string) $app['config']->get('admin.email', '');
    $password = (string) $app['config']->get('admin.password', '');
    $name = (string) $app['config']->get('admin.name', 'Admin');

    if ($email === '' || $password === '') {
        echo "Admin creation skipped: ADMIN_EMAIL / ADMIN_PASSWORD not configured\n";
    } elseif (User::where('email', $email)->exists()) {
        echo "Admin user already exists\n";
    } else {
        $admin = User::create([
            'name' => $name,
            'email' => $email,
            'username' => $name,
            'password' => Hash::make($password),
        ]);
        $role = Role::where('slug', 'admin')->first();
        if ($role) {
            $admin->roles()->attach($role->id);
        }
        echo "Admin user created ({$email})\n";
    }

    echo "\nDone!\n";
} catch (Throwable $e) {
    echo get_class($e).': '.$e->getMessage()."\n".$e->getTraceAsString();
}
