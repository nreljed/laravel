<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

echo "=== Create Admin User ===\n\n";

$role = Role::where('slug', 'admin')->first();
if (! $role) {
    echo "ERROR: admin role not found. Run seeder first.\n";
    exit(1);
}

$user = User::firstOrCreate(
    ['email' => 'nstudio.webdesign@gmail.com'],
    [
        'name' => 'nstudio',
        'username' => 'nstudio',
        'display_name' => 'nstudio',
        'password' => Hash::make('azerty123/*'),
        'email_verified_at' => now(),
    ]
);

if ($user->wasRecentlyCreated) {
    echo "  Created: nstudio.webdesign@gmail.com\n";
} else {
    echo "  Updated: nstudio.webdesign@gmail.com (already existed)\n";
}

$user->roles()->sync([$role->id]);
echo "  Role assigned: admin\n";

echo "\nDone! Total users: ".User::count()."\n";
