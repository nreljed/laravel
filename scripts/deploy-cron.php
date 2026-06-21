#!/usr/bin/env php
<?php

$projectDir = __DIR__;
$versionFile = $projectDir . '/.deployed-version';
$lockFile = $projectDir . '/.deploy-lock';
$logFile = $projectDir . '/.deploy-log';
$repo = 'nreljed/laravel';

$githubToken = '';
$envFile = $projectDir . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile) as $line) {
        if (str_starts_with(trim($line), 'GITHUB_TOKEN=')) {
            $githubToken = trim(substr(trim($line), strlen('GITHUB_TOKEN=')));
            $githubToken = trim($githubToken, '"\'');
        }
    }
}

if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 600) {
    exit(0);
}
file_put_contents($lockFile, getmypid());

function log_msg($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
}

function github_api($url, $token) {
    $headers = ['User-Agent: CostBreaker360-Deploy/1.0', 'Accept: application/vnd.github.v3+json'];
    if ($token) $headers[] = "Authorization: Bearer $token";
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => 30]]);
    $response = @file_get_contents($url, false, $ctx);
    return $response ? json_decode($response, true) : null;
}

log_msg('Checking for updates...');

$releases = github_api("https://api.github.com/repos/$repo/releases?per_page=1", $githubToken);
if (!$releases || !isset($releases[0]['tag_name'])) {
    @unlink($lockFile);
    exit(0);
}

$latestRelease = $releases[0];
$latestTag = $latestRelease['tag_name'];

$currentTag = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '';
if ($latestTag === $currentTag) {
    @unlink($lockFile);
    exit(0);
}

$assetUrl = '';
foreach ($latestRelease['assets'] ?? [] as $asset) {
    if ($asset['name'] === 'deploy.zip') {
        $assetUrl = $asset['url'];
        break;
    }
}

if (!$assetUrl) {
    log_msg("No deploy.zip asset in release $latestTag");
    @unlink($lockFile);
    exit(0);
}

log_msg("New release: $latestTag (current: $currentTag)");

$zipFile = sys_get_temp_dir() . '/cb360-deploy-' . uniqid() . '.zip';
$headers = ['User-Agent: CostBreaker360', 'Accept: application/octet-stream'];
if ($githubToken) $headers[] = "Authorization: Bearer $githubToken";
$ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => 300]]);
$zipData = @file_get_contents($assetUrl, false, $ctx);

if ($zipData === false || strlen($zipData) < 100) {
    log_msg('Failed to download release asset');
    @unlink($lockFile);
    exit(1);
}
file_put_contents($zipFile, $zipData);
log_msg('Downloaded ' . round(strlen($zipData) / 1048576, 1) . ' MB');

$backupEnv = '';
$envPath = $projectDir . '/.env';
if (file_exists($envPath)) {
    $backupEnv = file_get_contents($envPath);
}

$zip = new ZipArchive;
if ($zip->open($zipFile) !== true) {
    log_msg('Failed to open ZIP');
    @unlink($zipFile); @unlink($lockFile);
    exit(1);
}

$zip->extractTo($projectDir);
$zip->close();
@unlink($zipFile);

if ($backupEnv) {
    file_put_contents($envPath, $backupEnv);
    log_msg('Restored .env');
}

file_put_contents($versionFile, $latestTag);
log_msg("New version: $latestTag");

$phpBin = PHP_BINARY;
chdir($projectDir);

$commands = [
    ['php artisan migrate', [$phpBin, 'artisan', 'migrate', '--force']],
    ['php artisan config:cache', [$phpBin, 'artisan', 'config:cache']],
    ['php artisan route:cache', [$phpBin, 'artisan', 'route:cache']],
    ['php artisan view:cache', [$phpBin, 'artisan', 'view:cache']],
    ['php artisan storage:link', [$phpBin, 'artisan', 'storage:link', '--force']],
];

foreach ($commands as [$label, $cmd]) {
    $out = []; $code = 0;
    exec(escapeshellarg($cmd[0]) . ' ' . implode(' ', array_map('escapeshellarg', array_slice($cmd, 1))) . ' 2>&1', $out, $code);
    log_msg("$label: exit=$code | " . implode('; ', array_slice($out, 0, 3)));
}

@unlink($lockFile);
log_msg("Deploy complete: $latestTag");