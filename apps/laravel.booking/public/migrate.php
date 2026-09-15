<?php

use Illuminate\Contracts\Console\Kernel;

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

$rawCmd = $_GET['cmd'] ?? 'list';
$parts = explode(' ', $rawCmd, 2);
$command = $parts[0];
$extraArgs = [];
if (isset($parts[1])) {
    parse_str($parts[1], $extraArgs);
}
if (isset($_GET['args'])) {
    parse_str($_GET['args'], $extraArgs);
}
header('Content-Type: text/plain');

try {
    $exitCode = $kernel->call($command, array_merge(['--force' => true], $extraArgs));
    echo "Exit code: $exitCode\n\n";
    echo $kernel->output();
} catch (Throwable $e) {
    echo get_class($e).': '.$e->getMessage();
}
