<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

/**
 * Standalone Recovery Endpoint
 *
 * Bootstraps the Laravel console kernel directly — bypasses HTTP middleware,
 * session, CSRF, and all web-kernel pipeline. Works even when Laravel's
 * web routes return 500.
 *
 * This file is designed to survive a broken vendor/autoloader:
 * - Requires critical classes directly (bypassing broken classmap)
 * - Can regenerate the autoloader via composer dump-autoload
 * - Can reinstall vendor from scratch if needed
 *
 * Usage:
 *   GET  /recover.php?token=...&cmd=recovery
 *   GET  /recover.php?token=...&cmd=status
 *   GET  /recover.php?token=...&cmd=fix-autoloader
 *   POST /recover.php  (token + cmd in body)
 *
 * DELETE this file after recovery is complete.
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ERROR | E_PARSE | E_WARNING);
ini_set('display_errors', '0');

// ── Helpers ──────────────────────────────────────────────────────────────

function jsonOut(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function parseEnvFile(string $path): array
{
    $vars = [];
    if (! is_file($path)) {
        return $vars;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $vars[trim($key)] = trim($value);
        }
    }

    return $vars;
}

function runShell(string $cmd, int $timeout = 120, array $envVars = []): array
{
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $env = $envVars ?: null;
    $proc = proc_open($cmd, $descriptors, $pipes, null, $env, ['bypass_shell' => true]);
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
        usleep(100_000);
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

$env = parseEnvFile($appRoot.'/.env');
$expected = $env['DEPLOY_TOKEN'] ?? null;
$token = $_REQUEST['token'] ?? null;

if (! $expected || ! $token || ! hash_equals((string) $expected, (string) $token)) {
    jsonOut(['error' => 'Invalid or missing deploy token.'], 403);
}

// ── Command allowlist ────────────────────────────────────────────────────

$ALLOWED = [
    'recovery', 'fix-autoloader', 'status',
    'package:discover', 'config:cache', 'config:clear',
    'route:cache', 'route:clear',
    'view:cache', 'view:clear',
    'cache:clear', 'cache:forget', 'cache:prune-stale-tags',
    'migrate', 'migrate:status', 'migrate:fresh', 'migrate:rollback',
    'db:seed', 'db:is-empty',
    'storage:link',
    'app:create-admin',
    'permissions:sync', 'permissions:list',
    'queue:restart',
    'schedule:run', 'schedule:list',
    'about', 'route:list', 'event:list',
    'down', 'up',
];

$cmd = $_REQUEST['cmd'] ?? $_REQUEST['command'] ?? 'status';
$cmd = trim((string) $cmd);

// Parse args from ?args=--force,--seed or JSON body
$argsRaw = $_REQUEST['args'] ?? '';
if (is_string($argsRaw) && $argsRaw !== '') {
    $args = array_map('trim', explode(',', $argsRaw));
} elseif (is_array($argsRaw)) {
    $args = $argsRaw;
} else {
    $args = [];
}

// ── Status (no bootstrap needed) ─────────────────────────────────────────

if ($cmd === 'status') {
    $checks = [
        'php_version' => PHP_VERSION,
        'php_os' => PHP_OS,
        'services_cache' => is_file($appRoot.'/bootstrap/cache/services.php'),
        'packages_cache' => is_file($appRoot.'/bootstrap/cache/packages.php'),
        'deployed_version' => is_file($appRoot.'/.deployed-version')
            ? trim(file_get_contents($appRoot.'/.deployed-version'))
            : 'missing',
        'env_exists' => is_file($appRoot.'/.env'),
        'storage_link' => is_link($appRoot.'/storage'),
        'vendor_exists' => is_dir($appRoot.'/vendor/laravel'),
        'autoload_static_exists' => is_file($appRoot.'/vendor/composer/autoload_static.php'),
        'application_php_exists' => is_file($appRoot.'/vendor/laravel/framework/src/Illuminate/Foundation/Application.php'),
        'bootstrap_app_exists' => is_file($appRoot.'/bootstrap/app.php'),
    ];
    jsonOut(['status' => 'ok', 'checks' => $checks]);
}

// ── Fix autoloader ───────────────────────────────────────────────────────

if ($cmd === 'fix-autoloader') {
    set_time_limit(300);
    $results = [];

    // Step 1: Find or download composer
    $composer = null;
    foreach ([$appRoot.'/composer.phar', $appRoot.'/composer'] as $c) {
        if (is_file($c)) {
            $composer = $c;
            break;
        }
    }
    if (! $composer) {
        $results['download_composer'] = 'Downloading composer...';
        $dl = runShell('curl -sS https://getcomposer.org/installer -o '.escapeshellarg($appRoot.'/composer-setup.php'), 60);
        if ($dl['exit'] !== 0) {
            jsonOut(['status' => 'error', 'step' => 'download_composer', 'error' => $dl['stderr'] ?: $dl['stdout']], 500);
        }
        $inst = runShell('php '.escapeshellarg($appRoot.'/composer-setup.php').' --quiet --install-dir='.escapeshellarg($appRoot), 60);
        if ($inst['exit'] !== 0) {
            jsonOut(['status' => 'error', 'step' => 'install_composer', 'error' => $inst['stderr'] ?: $inst['stdout']], 500);
        }
        @unlink($appRoot.'/composer-setup.php');
        $composer = $appRoot.'/composer.phar';
        $results['download_composer'] = 'composer.phar downloaded';
    }

    // Step 2: composer install --no-dev --optimize-autoloader
    // Environment variables MUST be passed via proc_open's env param (not shell
    // syntax) because bypass_shell skips the shell entirely.
    $envAuth = $env['GITHUB_TOKEN'] ?? '';
    $authJson = json_encode(['github-oauth' => ['github.com' => $envAuth]]);

    $install = runShell(
        'php '.escapeshellarg($composer).' install --no-dev --optimize-autoloader --no-interaction --no-scripts --working-dir='.escapeshellarg($appRoot),
        300,
        ['COMPOSER_AUTH' => $authJson]
    );
    $results['composer_install'] = [
        'exit' => $install['exit'],
        'output' => substr($install['stdout'], -2000),
    ];
    if ($install['exit'] !== 0) {
        $results['composer_install']['stderr'] = substr($install['stderr'], -1000);
    }

    // Step 3: package:discover
    $discover = runShell('php '.escapeshellarg($appRoot.'/artisan').' package:discover --ansi --force', 60);
    $results['package_discover'] = [
        'exit' => $discover['exit'],
        'output' => trim(substr($discover['stdout'], -1000)),
    ];

    jsonOut(['status' => $install['exit'] === 0 ? 'ok' : 'error', 'results' => $results]);
}

// ── Bootstrap for artisan commands ───────────────────────────────────────

try {
    require $appRoot.'/vendor/autoload.php';
} catch (Throwable $e) {
    jsonOut([
        'status' => 'error',
        'phase' => 'autoload',
        'error' => $e->getMessage(),
        'file' => $e->getFile().':'.$e->getLine(),
        'hint' => 'Run ?cmd=fix-autoloader to regenerate the autoloader.',
    ], 500);
}

// Try to load Application class — if autoloader is broken, require the file directly
if (! class_exists(Application::class, false)) {
    $appFile = $appRoot.'/vendor/laravel/framework/src/Illuminate/Foundation/Application.php';
    if (is_file($appFile)) {
        try {
            require $appFile;
        } catch (Throwable $e) {
            jsonOut([
                'status' => 'error',
                'phase' => 'load_application_class',
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
                'hint' => 'Run ?cmd=fix-autoloader to regenerate the autoloader.',
            ], 500);
        }
    }
}

try {
    $app = require $appRoot.'/bootstrap/app.php';
} catch (Throwable $e) {
    // If bootstrap/app.php fails, create Application manually
    try {
        $app = new Application($appRoot);
        $app->bootstrap();
    } catch (Throwable $e2) {
        jsonOut([
            'status' => 'error',
            'phase' => 'bootstrap',
            'error' => $e->getMessage(),
            'file' => $e->getFile().':'.$e->getLine(),
            'fallback_error' => $e2->getMessage(),
            'fallback_file' => $e2->getFile().':'.$e2->getLine(),
            'hint' => 'Run ?cmd=fix-autoloader to regenerate the autoloader.',
        ], 500);
    }
}

// ── Handle 'recovery' meta-command ───────────────────────────────────────

if ($cmd === 'recovery') {
    set_time_limit(300);

    try {
        $kernel = $app->make(Kernel::class);
    } catch (Throwable $e) {
        jsonOut([
            'status' => 'error',
            'phase' => 'kernel_resolution',
            'error' => $e->getMessage(),
            'file' => $e->getFile().':'.$e->getLine(),
        ], 500);
    }

    $sequence = [
        'package:discover' => ['--force' => true],
        'migrate' => ['--force' => true],
        'app:create-admin' => [
            '--no-interaction' => true,
            '--email' => $env['ADMIN_EMAIL'] ?? '',
            '--password' => $env['ADMIN_PASSWORD'] ?? '',
            '--name' => $env['ADMIN_NAME'] ?? 'Admin',
        ],
        'config:cache' => [],
        'route:cache' => [],
        'view:cache' => [],
        'storage:link' => ['--force' => true],
        'permissions:sync' => [],
    ];

    $results = [];
    foreach ($sequence as $c => $p) {
        try {
            $kernel->call($c, $p);
            $output = trim($kernel->getOutput());
            $results[$c] = ['status' => 'ok', 'output' => $output ?: 'done'];
        } catch (Throwable $e) {
            $results[$c] = ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    try {
        file_put_contents($appRoot.'/.deployed-version', 'recovered-'.date('c'));
    } catch (Throwable $e) {
        // non-fatal
    }

    jsonOut(['status' => 'ok', 'sequence' => $results]);
}

// ── Validate command ─────────────────────────────────────────────────────

if (! in_array($cmd, $ALLOWED, true)) {
    jsonOut([
        'error' => "Command not allowed: {$cmd}",
        'allowed' => $ALLOWED,
    ], 403);
}

// ── Run single command ───────────────────────────────────────────────────

try {
    $kernel = $app->make(Kernel::class);
} catch (Throwable $e) {
    jsonOut([
        'status' => 'error',
        'phase' => 'kernel_resolution',
        'command' => $cmd,
        'error' => $e->getMessage(),
        'file' => $e->getFile().':'.$e->getLine(),
    ], 500);
}

set_time_limit(300);

try {
    $exitCode = $kernel->call($cmd, $args);
    $output = trim($kernel->getOutput());

    jsonOut([
        'status' => $exitCode === 0 ? 'ok' : 'error',
        'command' => $cmd,
        'args' => $args,
        'exit_code' => $exitCode,
        'output' => $output,
    ], $exitCode === 0 ? 200 : 500);
} catch (Throwable $e) {
    jsonOut([
        'status' => 'error',
        'command' => $cmd,
        'error' => $e->getMessage(),
        'file' => $e->getFile().':'.$e->getLine(),
    ], 500);
}
