<?php

/**
 * Standalone Vendor Repair Script
 *
 * ZERO Laravel dependencies — pure PHP only.
 * Downloads composer.phar and runs composer install to rebuild vendor/.
 * Works even when Laravel autoloader is completely broken.
 *
 * Usage:
 *   GET /fix-vendor.php?token=YOUR_DEPLOY_TOKEN
 *
 * DELETE this file after vendor/ is repaired.
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('max_execution_time', '600');

function jsonOut(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function runCommand(string $cmd, int $timeout = 300): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
    if (! is_resource($proc)) {
        return ['exit' => -1, 'stdout' => '', 'stderr' => 'Failed to start process'];
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $start = time();
    $stdout = '';
    $stderr = '';
    while (true) {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        if (time() - $start > $timeout) {
            proc_terminate($proc, 9);

            return ['exit' => -2, 'stdout' => $stdout, 'stderr' => $stderr."\nKilled after {$timeout}s"];
        }
        $status = proc_get_status($proc);
        if (! $status['running']) {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            break;
        }
        usleep(200_000);
    }
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
}

// ── Paths ────────────────────────────────────────────────────────────────
$publicDir = __DIR__;
$appRoot = dirname($publicDir);

// ── Token verification ───────────────────────────────────────────────────
$token = $_REQUEST['token'] ?? null;
if (! $token) {
    jsonOut(['error' => 'Missing token.'], 403);
}

// Read token from .env directly (no Laravel needed)
$envToken = null;
if (is_file($appRoot.'/.env')) {
    foreach (file($appRoot.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'DEPLOY_TOKEN=')) {
            $envToken = trim(substr($line, 13));
            break;
        }
    }
}

if (! $envToken || ! hash_equals((string) $envToken, (string) $token)) {
    jsonOut(['error' => 'Invalid token.'], 403);
}

// ── Read GitHub token from .env ──────────────────────────────────────────
$githubToken = null;
if (is_file($appRoot.'/.env')) {
    foreach (file($appRoot.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'GITHUB_TOKEN=')) {
            $githubToken = trim(substr($line, 13));
            break;
        }
    }
}

// ── Step 1: Backup current vendor ────────────────────────────────────────
$results = [];
$vendorDir = $appRoot.'/vendor';

if (is_dir($vendorDir)) {
    $backupDir = $appRoot.'/vendor.bak.'.date('Ymd_His');
    if (@rename($vendorDir, $backupDir)) {
        $results['backup'] = 'Moved old vendor/ to '.basename($backupDir);
    } else {
        $results['backup'] = 'Could not rename vendor/ (non-fatal, continuing)';
    }
}

// ── Step 2: Download composer.phar ───────────────────────────────────────
$composerPath = $appRoot.'/composer.phar';

if (! is_file($composerPath)) {
    $dl = @file_get_contents('https://getcomposer.org/installer', false, stream_context_create([
        'http' => ['timeout' => 60],
    ]));
    if ($dl === false) {
        jsonOut(['status' => 'error', 'step' => 'download_installer', 'error' => 'Could not download composer installer'], 500);
    }
    file_put_contents($appRoot.'/composer-setup.php', $dl);
    $inst = runCommand('php '.escapeshellarg($appRoot.'/composer-setup.php').' --quiet --install-dir='.escapeshellarg($appRoot), 60);
    @unlink($appRoot.'/composer-setup.php');
    if ($inst['exit'] !== 0) {
        jsonOut(['status' => 'error', 'step' => 'install_composer', 'error' => $inst['stderr'] ?: $inst['stdout']], 500);
    }
    $results['composer'] = 'composer.phar downloaded';
} else {
    $results['composer'] = 'composer.phar already exists';
}

// ── Step 3: composer install ─────────────────────────────────────────────
$envJson = ['github-oauth' => ['github.com' => $githubToken ?: '']];
$envVars = ['COMPOSER_AUTH' => json_encode($envJson)];

$install = runCommand(
    'php '.escapeshellarg($composerPath).' install --no-dev --optimize-autoloader --no-interaction --no-scripts --working-dir='.escapeshellarg($appRoot),
    600,
);
$results['composer_install'] = [
    'exit' => $install['exit'],
    'output' => substr($install['stdout'], -3000),
];
if ($install['exit'] !== 0) {
    $results['composer_install']['stderr'] = substr($install['stderr'], -2000);
}

// ── Step 4: package:discover ─────────────────────────────────────────────
if ($install['exit'] === 0 && is_file($appRoot.'/artisan')) {
    $discover = runCommand('php '.escapeshellarg($appRoot.'/artisan').' package:discover --ansi --force', 60);
    $results['package_discover'] = [
        'exit' => $discover['exit'],
        'output' => trim(substr($discover['stdout'], -1000)),
    ];
}

// ── Step 5: Verify Application class exists ──────────────────────────────
$appFile = $vendorDir.'/laravel/framework/src/Illuminate/Foundation/Application.php';
$results['verify'] = [
    'vendor_dir' => is_dir($vendorDir),
    'autoload_php' => is_file($vendorDir.'/autoload.php'),
    'application_class' => is_file($appFile),
];

// ── Step 6: Test autoloader ──────────────────────────────────────────────
if (is_file($vendorDir.'/autoload.php')) {
    @require_once $vendorDir.'/autoload.php';
    $results['verify']['classmap_exists'] = is_file($vendorDir.'/composer/autoload_classmap.php');
    $results['verify']['psr4_exists'] = is_file($vendorDir.'/composer/autoload_psr4.php');
}

$results['total_time'] = (time() - $_SERVER['REQUEST_TIME']).'s';

jsonOut([
    'status' => $install['exit'] === 0 ? 'ok' : 'error',
    'results' => $results,
]);
