<?php

/**
 * Cron-based deploy check script.
 *
 * Usage: php scripts/deploy-cron.php
 *
 * Replicates DeployController::check() for cron-based deployments.
 * Logs output to .deploy-log in the project root.
 *
 * Environment:
 *   GITHUB_TOKEN in .env — GitHub PAT with Contents: Read & Write
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

$logFile = base_path('.deploy-log');
$lockFile = base_path('.deploy-lock');
$versionFile = base_path('.deployed-version');
$envPath = base_path('.env');
$repo = 'nreljed/laravel.shop';

function logMsg(string $msg): void
{
    global $logFile;
    $line = '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL;
    echo $line;
    File::append($logFile, $line);
}

// Lock check (10 min TTL)
if (File::exists($lockFile) && (time() - File::lastModified($lockFile)) < 600) {
    logMsg('Lock active — skipping');
    exit(0);
}
File::put($lockFile, getmypid());
register_shutdown_function(function () use ($lockFile): void {
    File::delete($lockFile);
});

// Parse GITHUB_TOKEN from .env
$githubToken = '';
if (File::exists($envPath)) {
    foreach (explode("\n", File::get($envPath)) as $line) {
        if (str_starts_with(trim($line), 'GITHUB_TOKEN=')) {
            $githubToken = trim(substr(trim($line), strlen('GITHUB_TOKEN=')));
            $githubToken = trim($githubToken, '"\'');
        }
    }
}

if (! $githubToken) {
    logMsg('GITHUB_TOKEN not found in .env');
    exit(1);
}

// Fetch latest release
logMsg('Checking '.$repo.' for new release...');
$response = Http::withHeaders([
    'User-Agent' => 'CostBreaker360',
    'Accept' => 'application/vnd.github.v3+json',
])->withToken($githubToken)
    ->timeout(30)
    ->get("https://api.github.com/repos/$repo/releases?per_page=1");

if (! $response->successful()) {
    logMsg('GitHub API error: '.$response->status());
    exit(1);
}

$releases = $response->json();
if (! $releases || ! isset($releases[0]['tag_name'])) {
    logMsg('No releases found');
    exit(0);
}

$latestTag = $releases[0]['tag_name'];
$currentTag = File::exists($versionFile) ? trim(File::get($versionFile)) : '';

if ($latestTag === $currentTag) {
    logMsg("Already at $latestTag — nothing to do");
    exit(0);
}

// Find deploy.zip asset
$assetUrl = '';
foreach ($releases[0]['assets'] ?? [] as $asset) {
    if ($asset['name'] === 'deploy.zip') {
        $assetUrl = $asset['url'];
        break;
    }
}

if (! $assetUrl) {
    logMsg('No deploy.zip in release');
    exit(1);
}

logMsg("New release: $latestTag — downloading...");

// Download deploy.zip
$zipResponse = Http::withHeaders([
    'User-Agent' => 'CostBreaker360',
    'Accept' => 'application/octet-stream',
])->withToken($githubToken)
    ->timeout(300)
    ->get($assetUrl);

if (! $zipResponse->successful() || strlen($zipResponse->body()) < 100) {
    logMsg('Download failed');
    exit(1);
}

$zipFile = storage_path('app/deploy-'.uniqid().'.zip');
File::put($zipFile, $zipResponse->body());
logMsg('Downloaded '.round(strlen($zipResponse->body()) / 1048576, 1).' MB');

// Backup .env
$backupEnv = File::exists($envPath) ? File::get($envPath) : '';

// Build a lookup set of all entries in the zip (excluding dir markers)
$zip = new ZipArchive;
if ($zip->open($zipFile) !== true) {
    logMsg('Failed to open ZIP');
    exit(1);
}
$zipFiles = [];
for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    if ($stat && $stat['name'] !== '' && ! str_ends_with($stat['name'], '/')) {
        $zipFiles[str_replace('\\', '/', $stat['name'])] = true;
    }
}

$zip->extractTo(base_path());
$zip->close();
File::delete($zipFile);
logMsg('Extracted to '.base_path());

// Remove stale files — files on disk that are NOT in the zip
$staleDirs = ['app', 'config', 'database', 'public', 'resources', 'routes', 'bootstrap'];
$protected = ['bootstrap/cache/'];
$rootLen = strlen(base_path()) + 1;
$removed = 0;
foreach ($staleDirs as $dir) {
    $full = base_path($dir);
    if (! is_dir($full)) {
        continue;
    }
    $files = File::allFiles($full);
    foreach ($files as $file) {
        $rel = str_replace('\\', '/', substr($file->getPathname(), $rootLen));
        $skip = false;
        foreach ($protected as $p) {
            if (str_starts_with($rel, $p)) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
        if (! str_ends_with($rel, '.php') && ! str_ends_with($rel, '.js') && ! str_ends_with($rel, '.ts') && ! str_ends_with($rel, '.tsx')) {
            continue;
        }
        if (! isset($zipFiles[$rel])) {
            File::delete($file->getPathname());
            $removed++;
        }
    }
}
if ($removed > 0) {
    logMsg("Removed $removed stale files");
}

// Restore .env
if ($backupEnv) {
    File::put($envPath, $backupEnv);
    logMsg('Restored .env');
}

// Write version marker
File::put($versionFile, $latestTag);
logMsg("New version: $latestTag");

// Run artisan commands
$commands = [
    'package:discover' => [],
    'migrate' => ['--force' => true],
    'permissions:sync' => [],
    'config:cache' => [],
    'route:cache' => [],
    'view:cache' => [],
    'storage:link' => ['--force' => true],
];

foreach ($commands as $cmd => $params) {
    try {
        Artisan::call($cmd, $params);
        logMsg("$cmd: ".strtok(trim(Artisan::output()), "\n"));
    } catch (Exception $e) {
        logMsg("$cmd: ERROR ".$e->getMessage());
    }
}

logMsg('Deploy complete');
