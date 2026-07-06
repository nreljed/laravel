<?php

set_time_limit(300);
error_reporting(E_ALL);
ini_set('display_errors', '1');
echo "<pre>\n";

// Try multiple URLs in case some are blocked
$urls = [
    'https://github.com/nreljed/laravel/releases/download/v0.1.10/deploy.zip',
    'https://github.com/nreljed/laravel/archive/refs/heads/main.zip',
];

echo "Checking connectivity...\n";
$test = @file_get_contents('https://github.com', false, stream_context_create(['http' => ['timeout' => 5, 'method' => 'GET']]));
if ($test === false) {
    echo "Cannot reach github.com via file_get_contents. Trying cURL...\n";
    $ch = curl_init('https://github.com');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_USERAGENT => 'Mozilla/5.0']);
    $curlTest = curl_exec($ch);
    curl_close($ch);
    if ($curlTest === false) {
        echo "cURL also failed. Outbound HTTPS is blocked on this server.\n";
        echo "\nManual recovery needed:\n";
        echo "1. Download from: https://github.com/nreljed/laravel/releases/download/v0.1.10/deploy.zip\n";
        echo '2. Upload to '.__DIR__."/deploy.zip via cPanel File Manager\n";
        echo "3. Then re-run this script (it will detect the local zip)\n";
        exit;
    }
}

$zip = __DIR__.'/deploy.zip';
if (! file_exists($zip)) {
    echo "Downloading v0.1.10 deploy.zip...\n";
    $ok = false;
    foreach ($urls as $url) {
        echo "Trying: $url\n";
        if (function_exists('curl_version')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => 1, CURLOPT_USERAGENT => 'Mozilla/5.0',
                CURLOPT_FOLLOWLOCATION => 1, CURLOPT_TIMEOUT => 300,
            ]);
            $data = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            if ($info['http_code'] == 200 && strlen($data) > 100000) {
                $ok = true;
                break;
            }
        } else {
            $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 300, 'method' => 'GET']]));
            if ($data !== false && strlen($data) > 100000) {
                $ok = true;
                break;
            }
        }
    }
    if (! $ok) {
        echo "Download failed from all URLs\n";
        exit;
    }
    file_put_contents($zip, $data);
} else {
    echo "Found existing deploy.zip\n";
}

echo 'Size: '.round(filesize($zip) / 1048576, 1)." MB\n";

$envBackup = file_exists(__DIR__.'/../.env') ? file_get_contents(__DIR__.'/../.env') : '';

echo "Extracting...\n";
$z = new ZipArchive;
if ($z->open($zip) !== true) {
    echo "Failed to open ZIP\n";
    exit;
}
$z->extractTo(__DIR__.'/..');
$z->close();
unlink($zip);
echo "Extracted OK\n";

if ($envBackup) {
    file_put_contents(__DIR__.'/../.env', $envBackup);
    echo "Restored .env\n";
}

echo "Running artisan commands...\n";
$base = __DIR__.'/..';
$cmds = [
    'php artisan package:discover --ansi',
    'php artisan migrate --force',
    'php artisan config:cache',
    'php artisan route:cache',
    'php artisan view:cache',
];
foreach ($cmds as $cmd) {
    echo "> $cmd\n";
    passthru("cd \"$base\" && $cmd 2>&1");
    echo "\n";
}

echo "\nDone! Site should be back up.\n";
unlink(__FILE__);
