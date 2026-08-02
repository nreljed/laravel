<?php

use Illuminate\Contracts\Console\Kernel;
use Nreljed\DashboardCore\Models\User;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$users = User::all(['id', 'username', 'name', 'email']);
foreach ($users as $u) {
    echo "{$u->id}\t{$u->username}\t{$u->name}\t{$u->email}\n";
}
