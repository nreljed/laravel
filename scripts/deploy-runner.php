<?php

declare(strict_types=1);

/**
 * deploy-runner.php — standalone, token-gated deploy control plane.
 *
 * A zero-framework "runner" that is deliberately capable of running when the
 * app is dead (missing vendor/, half-swapped release, fatal during bootstrap):
 * it only uses built-in filesystem, ZipArchive and string functions.
 *
 * Usage (HTTP, after CI or an operator uploads this file to the web root):
 *
 *   https://<site>/runner.php?key=<DEPLOY_TOKEN>&action=status
 *   ...&action=recover | install | health | logs | prune
 *
 * Auth: the key must hash_equals the DEPLOY_TOKEN read from the nearest
 * ancestor `artisan` + `.env` directory. Env values are read only for
 * DEPLOY_TOKEN and GITHUB_TOKEN.
 *
 * Actions:
 *   status    - inventory: version marker, vendor state, stranded staging
 *               backups, deploy.zip presence, symlink/ZipArchive availability,
 *               PHP capabilities, release-layout topology.
 *   recover   - heal a killed install: restore the stranded vendor backup, or
 *               when no backup exists, run install against ROOT/deploy.zip.
 *   install   - safe-swap ROOT/deploy.zip into place (stage -> boot-check ->
 *               vendor-last swap, mirroring platform DeployService).
 *   health    - quick liveness probe (JSON with app-visible markers).
 *   prune     - remove stale deploy artifacts under storage/app (aborted-stage
 *               leaves: deploy-*.zip downloads + deploy-staging-* trees). Keeps
 *               the newest stranded *-vendor-backup when live vendor/ is missing
 *               (that backup is the recovery path), and never touches live
 *               storage, public/build or bootstrap/cache.
 *
 * The file is structured so tests can include it and call the pure functions
 * without going through HTTP.
 */
if (! defined('DEPLOY_RUNNER_BOOTSTRAP')) {
    define('DEPLOY_RUNNER_BOOTSTRAP', true);
}

/**
 * @return array{root: string, home: string, error?: string}
 */
function rd_find_root(): array
{
    $dir = __DIR__;

    while (true) {
        if (is_file($dir.'/.env') && is_file($dir.'/artisan')) {
            $root = $dir;
            break;
        }

        $parent = dirname($dir);
        if ($parent === $dir) {
            return ['root' => '', 'home' => '', 'error' => 'No artisan/.env ancestor found from '.__DIR__];
        }

        $dir = $parent;
    }

    // Release-model layout: ROOT sits inside releases/<ts>/ and the control
    // plane operates from the home dir (parent of releases/).
    $home = $root;
    $candidate = dirname($root);

    if (is_dir($candidate.'/releases') && ! str_ends_with($root, DIRECTORY_SEPARATOR.'releases')) {
        $home = $candidate;
    }

    return ['root' => $root, 'home' => $home];
}

/**
 * Read a key from a Laravel-style .env file.
 */
function rd_env_get(string $path, string $key): string
{
    if (! is_file($path)) {
        return '';
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $name = strtok($line, '=');
        if ($name !== false && trim($name) === $key) {
            $value = trim(substr($line, strlen($name) + 1));
            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            return $value;
        }
    }

    return '';
}

/**
 * Authorize a request against the root .env DEPLOY_TOKEN.
 */
function rd_authorize(array $topo, string $key): bool
{
    $token = rd_env_get($topo['root'].'/.env', 'DEPLOY_TOKEN');

    if ($token === '') {
        $token = rd_env_get($topo['home'].'/.env', 'DEPLOY_TOKEN');
    }

    return $token !== '' && hash_equals($token, $key);
}

/**
 * Locate a stranded `deploy-staging-*-vendor-backup` directory (the tell-tale
 * of an install killed between "old vendor moved aside" and the swap).
 *
 * @return string Absolute path of the newest stranded backup, or ''.
 */
function rd_find_stranded_backup(string $root): string
{
    $pattern = $root.'/storage/app/deploy-staging-*-vendor-backup';
    $matches = glob($pattern) ?: [];

    if ($matches === []) {
        return '';
    }

    usort($matches, fn (string $a, string $b): int => (int) @filemtime($b) <=> (int) @filemtime($a));

    return $matches[0];
}

/**
 * @return array<int, string>
 */
function rd_clear_bootstrap_cache(string $root): array
{
    $output = [];
    $cacheDir = $root.'/bootstrap/cache';

    if (is_dir($cacheDir)) {
        foreach (scandir($cacheDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $cacheDir.'/'.$entry;

            if (is_file($path)) {
                @unlink($path);

                continue;
            }

            if (is_dir($path)) {
                rd_rrmdir($path);
            }
        }
    } else {
        @mkdir($cacheDir, 0755, true);
    }

    $output[] = 'Cleared bootstrap/cache/';

    return $output;
}

/**
 * Recursive directory delete.
 */
function rd_rrmdir(string $dir): bool
{
    if (! is_dir($dir)) {
        return false;
    }

    $items = scandir($dir) ?: [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir.'/'.$item;

        if (is_dir($path)) {
            rd_rrmdir($path);
        } else {
            @unlink($path);
        }
    }

    return @rmdir($dir);
}

/**
 * Restore a stranded vendor backup back to the root so the app boots on the
 * version that was live before the interrupted run.
 *
 * @return array{restored: bool, output: array<int, string>}
 */
function rd_restore_stranded_backup(string $root, array &$output): array
{
    $backup = rd_find_stranded_backup($root);

    if ($backup === '') {
        return ['restored' => false, 'output' => []];
    }

    $vendor = $root.'/vendor';

    if (is_dir($vendor)) {
        // Live vendor already present — the stranded backup is stale.
        rd_rrmdir($backup);
        $output[] = 'Removed stale vendor backup (live vendor present): '.basename($backup);

        return ['restored' => true, 'output' => $output];
    }

    if (! rename($backup, $vendor)) {
        $output[] = 'Failed to restore vendor backup from '.$backup;

        return ['restored' => false, 'output' => $output];
    }

    array_push($output, ...rd_clear_bootstrap_cache($root));
    $output[] = 'Restored vendor/ from interrupted-install backup: '.basename($backup);

    return ['restored' => true, 'output' => $output];
}

/**
 * Capability probe — what can this runtime do?
 *
 * @return array<string, bool|string>
 */
function rd_capabilities(string $root): array
{
    $symlinkOk = false;

    if (function_exists('symlink')) {
        $probe = $root.'/.deploy-runner-symlink-probe';
        $target = __FILE__;

        if (@symlink($target, $probe)) {
            $symlinkOk = is_link($probe);
            @unlink($probe);
        }
    }

    return [
        'php' => PHP_VERSION,
        'zip' => class_exists('ZipArchive'),
        'symlink' => $symlinkOk,
        'exec' => function_exists('exec') && ! in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true),
        'proc_open' => function_exists('proc_open'),
        'set_time_limit' => function_exists('set_time_limit'),
    ];
}

/**
 * @return array<string, mixed>
 */
function rd_action_status(array $topo): array
{
    $root = $topo['root'];
    $output = [];
    $vendorAutoload = is_file($root.'/vendor/autoload.php');
    $versionFile = $root.'/.deployed-version';
    $version = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '';
    $deployZip = $root.'/deploy.zip';
    $stranded = rd_find_stranded_backup($root);

    $releases = [];
    $releasesDir = $topo['home'].'/releases';
    $currentSymlink = '';

    if (is_dir($releasesDir)) {
        foreach (glob($releasesDir.'/*', GLOB_ONLYDIR) ?: [] as $release) {
            $releases[] = [
                'name' => basename($release),
                'version' => is_file($release.'/.deployed-version')
                    ? trim((string) file_get_contents($release.'/.deployed-version'))
                    : '',
            ];
        }

        if (is_link($topo['home'].'/current')) {
            $currentSymlink = (string) readlink($topo['home'].'/current');
        }
    }

    $output[] = 'Root: '.$root;
    $output[] = $vendorAutoload ? 'vendor/autoload.php OK' : 'vendor/autoload.php MISSING';
    $output[] = $stranded !== '' ? 'Stranded vendor backup: '.$stranded : 'No stranded vendor backup';

    return [
        'ok' => true,
        'action' => 'status',
        'root' => $root,
        'home' => $topo['home'],
        'version' => $version,
        'vendor_ok' => $vendorAutoload,
        'deploy_zip' => [
            'exists' => is_file($deployZip),
            'size' => is_file($deployZip) ? filesize($deployZip) : 0,
        ],
        'stranded_backup' => $stranded,
        'capabilities' => rd_capabilities($root),
        'releases' => $releases,
        'current_symlink' => $currentSymlink,
        'output' => $output,
    ];
}

/**
 * @return array<string, mixed>
 */
function rd_action_recover(array $topo): array
{
    $root = $topo['root'];
    $output = [];

    ['restored' => $restored, 'output' => $output] = rd_restore_stranded_backup($root, $output);

    if ($restored) {
        return [
            'ok' => true,
            'action' => 'recover',
            'status' => 'healed_from_backup',
            'output' => $output,
        ];
    }

    if (! is_file($root.'/vendor/autoload.php') && is_file($root.'/deploy.zip')) {
        return rd_action_install($topo);
    }

    $output[] = 'vendor/ present and no stranded backup — nothing to heal.';

    return [
        'ok' => true,
        'action' => 'recover',
        'status' => 'nothing_to_do',
        'output' => $output,
    ];
}

/**
 * Boot-check a staged install. Without the app running, we validate the tree
 * structurally and, when exec is available, run `php artisan --version`.
 */
function rd_boot_check(string $stage, array $topo, array &$output): bool
{
    if (! is_file($stage.'/vendor/autoload.php')) {
        return false;
    }

    if (! is_file($stage.'/artisan') || ! is_file($stage.'/bootstrap/app.php')) {
        $output[] = 'Boot check failed: staged tree missing artisan/bootstrap';

        return false;
    }

    $capabilities = rd_capabilities($topo['root']);

    if ($capabilities['exec'] === true) {
        $cmd = 'cd '.escapeshellarg($stage).' && '.PHP_BINARY.' artisan --version 2>&1';

        @exec($cmd, $execOutput, $exitCode);

        if ($exitCode !== 0) {
            $output[] = 'Boot check (exec) failed: '.implode(' | ', $execOutput);

            return false;
        }

        if (trim(implode(' ', $execOutput)) !== '') {
            $output[] = 'Boot check (exec) passed: '.trim(implode(' ', $execOutput));
        }

        return true;
    }

    $output[] = 'Boot check passed (structural only — exec unavailable)';

    return true;
}

/**
 * Extract ROOT/deploy.zip into staging, boot-check, then swap the staged tree
 * into the live root with the kill-window-closed ordering (vendor moved last).
 *
 * @return array<string, mixed>
 */
function rd_action_install(array $topo, string $version = ''): array
{
    $root = $topo['root'];
    $zipPath = $root.'/deploy.zip';
    $output = [];

    if (! is_file($zipPath)) {
        return [
            'ok' => false,
            'action' => 'install',
            'status' => 'no_deploy_zip',
            'output' => ['No ROOT/deploy.zip to install.'],
        ];
    }

    // Heal any interrupted previous run first.
    ['restored' => $restored, 'output' => $output] = rd_restore_stranded_backup($root, $output);
    if ($restored) {
        return [
            'ok' => false,
            'action' => 'install',
            'status' => 'healed_previous_run',
            'output' => ['Previous interrupted install healed; this install skipped.'],
        ];
    }

    $zip = new ZipArchive;

    if ($zip->open($zipPath) !== true) {
        return [
            'ok' => false,
            'action' => 'install',
            'status' => 'bad_zip',
            'output' => ['Failed to open deploy.zip.'],
        ];
    }

    $stage = $root.'/storage/app/deploy-staging-'.uniqid('', true);
    @mkdir($stage, 0755, true);

    $extracted = $zip->extractTo($stage);
    $zip->close();

    if (! $extracted || ! is_file($stage.'/vendor/autoload.php')) {
        rd_rrmdir($stage);
        $output[] = 'Extraction failed or staged vendor incomplete';

        return ['ok' => false, 'action' => 'install', 'status' => 'extract_failed', 'output' => $output];
    }

    if (! rd_boot_check($stage, $topo, $output)) {
        rd_rrmdir($stage);
        $output[] = 'Boot check failed; release rejected (live install untouched)';

        return ['ok' => false, 'action' => 'install', 'status' => 'boot_check_failed', 'output' => $output];
    }

    // Swap, vendor last.
    try {
        array_push($output, ...rd_clear_bootstrap_cache($root));

        // Mirror all non-vendor, non-storage tree into the live root.
        foreach (scandir($stage) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $src = $stage.'/'.$entry;
            $dst = $root.'/'.$entry;

            if ($entry === 'vendor' || $entry === 'storage' || $entry === '.env') {
                continue;
            }

            if (is_dir($src)) {
                rd_copy_dir($src, $dst);
            } elseif (is_file($src)) {
                @copy($src, $dst);
            }
        }

        // Move the live vendor aside, then stage vendor in — millisecond window.
        $backup = $stage.'-vendor-backup';

        if (is_dir($root.'/vendor') && ! rename($root.'/vendor', $backup)) {
            throw new RuntimeException('Could not move live vendor/ aside.');
        }

        if (! rename($stage.'/vendor', $root.'/vendor')) {
            if (is_dir($backup)) {
                rename($backup, $root.'/vendor');
            }

            throw new RuntimeException('Could not move staged vendor/ into place.');
        }

        rd_rrmdir($stage);

        if (is_dir($backup)) {
            rd_rrmdir($backup);
        }

        $output[] = 'Swapped staged release into place (vendor last).';

        if ($version !== '') {
            file_put_contents($root.'/.deployed-version', $version);
            $output[] = "Version marker: $version";
        }
    } catch (Throwable $e) {
        rd_rrmdir($stage);
        $output[] = 'Install aborted: '.$e->getMessage();

        return ['ok' => false, 'action' => 'install', 'status' => 'swap_failed', 'output' => $output];
    }

    return ['ok' => true, 'action' => 'install', 'status' => 'installed', 'output' => $output];
}

/**
 * Recursive copy with destination merge (no symlink follow of loops).
 */
function rd_copy_dir(string $src, string $dst): void
{
    if (! is_dir($src)) {
        return;
    }

    if (! is_dir($dst)) {
        @mkdir($dst, 0755, true);
    }

    foreach (scandir($src) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $srcPath = $src.'/'.$entry;
        $dstPath = $dst.'/'.$entry;

        if (is_dir($srcPath)) {
            rd_copy_dir($srcPath, $dstPath);
        } elseif (is_file($srcPath)) {
            @copy($srcPath, $dstPath);
        }
    }
}

/**
 * @return array<string, mixed>
 */
function rd_action_health(array $topo): array
{
    $root = $topo['root'];

    return [
        'ok' => true,
        'action' => 'health',
        'vendor_autoload' => is_file($root.'/vendor/autoload.php'),
        'artisan' => is_file($root.'/artisan'),
        'env' => is_file($root.'/.env'),
        'index_php' => is_file($root.'/public/index.php'),
    ];
}

/**
 * Tail the app's own Laravel log plus the environment log channel, and stop
 * before any cached manifests get rewritten. Read-only: never touches files.
 *
 * @return array<string, mixed>
 */
function rd_action_logs(array $topo): array
{
    $root = $topo['root'];
    $output = [];
    $logDir = $root.'/storage/logs';

    $sources = glob($logDir.'/laravel-*.log') ?: [];

    if (is_file($logDir.'/laravel.log')) {
        array_unshift($sources, $logDir.'/laravel.log');
    }

    $output[] = 'log_dir: '.$logDir.' ('.(is_dir($logDir) ? 'exists' : 'MISSING').')';

    if ($sources === []) {
        $output[] = 'No log files found under storage/logs';

        return ['ok' => true, 'action' => 'logs', 'status' => 'no_logs', 'output' => $output];
    }

    $output[] = 'sources: '.implode(', ', array_map('basename', $sources));

    $tail = [];
    $last = end($sources) ?: $logDir.'/laravel.log';

    if (@filesize($last) > 512 * 1024) {
        $handle = fopen($last, 'r');
        if ($handle) {
            fseek($handle, -512 * 1024, SEEK_END);
            $chunk = fread($handle, 512 * 1024);
            fclose($handle);
            $tail = array_slice(preg_split('/\r\n|\r|\n/', (string) $chunk), -80);
        }
    } else {
        $tail = array_slice(file($last) ?: [], -80);
    }

    $tail = array_map('trim', $tail);

    // Drop noisy/predictable success lines to keep the answer readable.
    $noise = [
        'local.INFO: Command [cache:clear] executed successfully.',
        '#0 {main}',
    ];
    $filtered = array_values(array_filter(
        $tail,
        fn (string $line): bool => $line !== '' && ! in_array($line, $noise, true)
    ));

    $output[] = '--- tail of '.basename($last).' (last '.count($filtered).' lines) ---';
    foreach ($filtered as $line) {
        $output[] = $line;
    }

    return ['ok' => true, 'action' => 'logs', 'status' => 'ok', 'output' => $output];
}

/**
 * Remove stale deploy artifacts under storage/app/ that accumulate when a
 * deploy is interrupted before cleanup (old bootCheck throwing, OOM, timeout).
 *
 * Deletes:
 *   - deploy-*.zip        — stale GitHub-release downloads (24 MB each).
 *   - deploy-staging-*    — stale extraction trees (each a full app copy).
 *
 * Preserves:
 *   - deploy-staging-*-vendor-backup — when live vendor/autoload.php is missing,
 *     keep the newest one (that's the recovery path); delete older leftovers.
 *   - Everything outside storage/app (live storage, public/build, vendor/).
 *
 * @return array<string, mixed>
 */
function rd_action_prune(array $topo, ?int $budgetSeconds = null): array
{
    $root = $topo['root'];
    $storageApp = $root.'/storage/app';
    $output = [];

    if (! is_dir($storageApp)) {
        $output[] = 'No storage/app directory; nothing to prune.';

        return ['ok' => true, 'action' => 'prune', 'status' => 'no_storage_app', 'removed' => 0, 'freed_bytes' => 0, 'output' => $output];
    }

    // Budget: stay under max_execution_time. If the host caps it at 30s (and
    // set_time_limit was blocked at the HTTP entry), budget ~= 25s; with a
    // lifted cap, budget grows to at most 240s. Parts cut off by the budget
    // return a partial success so a repeat invoke continues the cleanup.
    if ($budgetSeconds === null) {
        $maxExec = (int) (@ini_get('max_execution_time') ?: 30);
        if ($maxExec <= 0 || $maxExec > 240) {
            $maxExec = 240;
        }
        $budgetSeconds = max(10, $maxExec - 5);
    }

    $budgetUntil = microtime(true) + $budgetSeconds;
    $removed = 0;
    $freedBytes = 0;
    $timedOut = false;

    // --- Stale deploy-*.zip downloads (cheap, never counts against budget) ---
    foreach (glob($storageApp.'/deploy-*.zip') ?: [] as $zipPath) {
        $size = @filesize($zipPath) ?: 0;
        $output[] = 'Removed '.basename($zipPath).' ('.rd_format_bytes($size).')';
        @unlink($zipPath);
        $removed++;
        $freedBytes += $size;
    }

    // --- Stale deploy-staging-* extraction trees (walk + delete in ONE pass) ---
    $vendorOk = is_file($root.'/vendor/autoload.php');
    $vendorBackups = glob($storageApp.'/deploy-staging-*-vendor-backup') ?: [];
    $nonBackups = [];
    $remaining = 0;

    foreach (glob($storageApp.'/deploy-staging-*') ?: [] as $stage) {
        if (str_ends_with(basename($stage), '-vendor-backup')) {
            continue;
        }

        $nonBackups[] = $stage;
    }

    foreach ($nonBackups as $stage) {
        if (microtime(true) > $budgetUntil) {
            $timedOut = true;
            $remaining++;
            $output[] = 'Time budget reached; deferred '.basename($stage).' to next prune run ('.round($budgetUntil - microtime(true) + $budgetSeconds).'s budget)';

            continue;
        }

        $collect = rd_rrmdir_collect($stage, $budgetUntil);
        $output[] = 'Removed '.basename($stage).' ('.rd_format_bytes($collect['freed']).')';
        $removed++;
        $freedBytes += $collect['freed'];

        if (! $collect['complete']) {
            $timedOut = true;
            $remaining++;
            $output[] = 'Time budget reached mid-tree; re-invoke prune to finish.';
        }
    }

    // Vendor backups: keep newest when live vendor/ is missing (recovery path);
    // delete everything when vendor/ is present (install completed, backups stale).
    if ($vendorOk) {
        foreach ($vendorBackups as $backup) {
            if (microtime(true) > $budgetUntil) {
                $timedOut = true;
                $remaining++;
                $output[] = 'Time budget reached; deferred '.basename($backup).' (vendor present) to next prune run.';

                continue;
            }

            $collect = rd_rrmdir_collect($backup, $budgetUntil);
            $output[] = 'Removed '.basename($backup).' (vendor present) ('.rd_format_bytes($collect['freed']).')';
            $removed++;
            $freedBytes += $collect['freed'];

            if (! $collect['complete']) {
                $timedOut = true;
                $remaining++;
                $output[] = 'Time budget reached mid-tree; re-invoke prune to finish.';
            }
        }
    } elseif ($vendorBackups !== []) {
        usort($vendorBackups, fn (string $a, string $b): int => (int) @filemtime($b) <=> (int) @filemtime($a));
        $kept = array_shift($vendorBackups);
        $output[] = 'Kept newest stranded vendor backup: '.basename($kept).' (live vendor missing)';

        foreach ($vendorBackups as $staleBackup) {
            if (microtime(true) > $budgetUntil) {
                $timedOut = true;
                $remaining++;
                $output[] = 'Time budget reached; deferred stale '.basename($staleBackup).' to next prune run.';

                continue;
            }

            $collect = rd_rrmdir_collect($staleBackup, $budgetUntil);
            $output[] = 'Removed stale '.basename($staleBackup).' ('.rd_format_bytes($collect['freed']).')';
            $removed++;
            $freedBytes += $collect['freed'];

            if (! $collect['complete']) {
                $timedOut = true;
                $remaining++;
                $output[] = 'Time budget reached mid-tree; re-invoke prune to finish.';
            }
        }
    }

    if ($removed === 0 && $remaining === 0) {
        $output[] = 'No stale deploy artifacts found under storage/app.';
    }

    return [
        'ok' => true,
        'action' => 'prune',
        'status' => $timedOut ? 'partial' : 'ok',
        'removed' => $removed,
        'freed_bytes' => $freedBytes,
        'freed' => rd_format_bytes($freedBytes),
        'partial' => $timedOut,
        'remaining' => $remaining,
        'output' => $output,
    ];
}

/**
 * Delete a directory tree in a single pass while collecting the freed bytes.
 *
 * Checks the time budget inside the walk so a host with a low
 * max_execution_time never dies mid-delete — the caller sees an incomplete
 * result and can re-invoke to continue (prune is idempotent).
 *
 * @return array{freed: int, complete: bool}
 */
function rd_rrmdir_collect(string $dir, float $budgetUntil = 0.0): array
{
    if (! is_dir($dir)) {
        return ['freed' => 0, 'complete' => true];
    }

    $freed = 0;

    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir.'/'.$item;

        if (is_dir($path)) {
            if ($budgetUntil > 0 && microtime(true) > $budgetUntil) {
                return ['freed' => $freed, 'complete' => false];
            }

            $sub = rd_rrmdir_collect($path, $budgetUntil);
            $freed += $sub['freed'];

            if (! $sub['complete']) {
                return ['freed' => $freed, 'complete' => false];
            }
        } else {
            $size = @filesize($path) ?: 0;
            @unlink($path);
            $freed += $size;
        }
    }

    @rmdir($dir);

    return ['freed' => $freed, 'complete' => true];
}

/**
 * Format a byte count for human-readable output.
 */
function rd_format_bytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $value = (float) $bytes;

    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }

    return round($value, 1).' '.$units[$i];
}

// --- HTTP entry point (skipped under tests that include this file) ---
if (PHP_SAPI !== 'cli' || isset($_SERVER['RUNNER_HTTP'])) {
    if (! defined('DEPLOY_RUNNER_HTTP_ENTRY')) {
        define('DEPLOY_RUNNER_HTTP_ENTRY', true);
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $payload = ['ok' => false, 'error' => 'unknown-action'];

    try {
        $topo = rd_find_root();

        if (isset($topo['error'])) {
            $payload = ['ok' => false, 'error' => $topo['error']];
        } else {
            $key = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
            $action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'status');

            if (! rd_authorize($topo, $key)) {
                $payload = ['ok' => false, 'error' => 'unauthorized'];
            } else {
                // Long-running actions (prune/install/recover) walk thousands of
                // files; shared hosts cap max_execution_time at 30s. Lift the
                // limit best-effort — internal budgets handle hosts that block it.
                if (function_exists('set_time_limit')) {
                    @set_time_limit(600);
                }

                $payload = match ($action) {
                    'status' => rd_action_status($topo),
                    'recover' => rd_action_recover($topo),
                    'install' => rd_action_install($topo, (string) ($_GET['version'] ?? $_POST['version'] ?? '')),
                    'health' => rd_action_health($topo),
                    'logs' => rd_action_logs($topo),
                    'prune' => rd_action_prune($topo),
                    default => ['ok' => false, 'error' => "unknown-action: {$action}"],
                };
            }
        }
    } catch (Throwable $e) {
        $payload = ['ok' => false, 'error' => $e->getMessage()];
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}
