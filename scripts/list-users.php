<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = App\Models\User::all(['id', 'username', 'name', 'email']);
foreach ($users as $u) {
    echo "{$u->id}\t{$u->username}\t{$u->name}\t{$u->email}\n";
}
