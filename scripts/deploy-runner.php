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
 *   ...&action=recover | install | release-install | release-rollback |
 *         release-cleanup | health | logs | prune
 *
 * Auth: the key must hash_equals the DEPLOY_TOKEN read from the nearest
 * ancestor `artisan` + `.env` directory. Env values are read only for
 * DEPLOY_TOKEN and GITHUB_TOKEN.
 *
 * Actions:
 *   status           - inventory: version marker, vendor state, stranded staging
 *                      backups, deploy.zip presence, symlink/ZipArchive
 *                      availability, PHP capabilities, release-layout topology.
 *   recover          - heal a broken layout: restore a stranded vendor backup
 *                      (legacy), or re-point current/public at the best release.
 *   install          - legacy in-place safe-swap of ROOT/deploy.zip (vendor
 *                      last), mirroring platform DeployService.
 *   release-install  - atomic Capistrano-style install: extract home/deploy.zip
 *                      into releases/<ts>, link shared .env/storage, then flip
 *                      `current` + webroot `public` symlinks. First run on a
 *                      legacy host migrates the in-place public/ dir aside.
 *                      Resumable: partial extracts continue on re-invoke.
 *   release-rollback - flip current/public back to the previous release (or
 *                      restore the legacy in-place webroot backup).
 *   release-cleanup  - retain the newest N releases, delete older ones.
 *   health           - quick liveness probe (JSON with app-visible markers).
 *   prune            - remove stale deploy artifacts under storage/app (aborted-stage
 *                      leaves: deploy-*.zip downloads + deploy-staging-* trees). Keeps
 *                      the newest stranded *-vendor-backup when live vendor/ is missing
 *                      (that backup is the recovery path), and never touches live
 *                      storage, public/build or bootstrap/cache.
 *
 * The file is structured so tests can include it and call the pure functions
 * without going through HTTP.
 */
if (! defined('DEPLOY_RUNNER_BOOTSTRAP')) {
    define('DEPLOY_RUNNER_BOOTSTRAP', true);
}

/**
 * Resolve the control plane topology:
 *
 *   legacy layout  — home === root === the in-place app dir (webroot = public/).
 *   release layout — home owns releases/<ts> + a `current` symlink; the app
 *                    root is the active release, and the webroot is the
 *                    `public` symlink -> releases/<active>/public. Shared state
 *                    (.env, storage) stays at home and is symlinked into each
 *                    release.
 *
 * The runner may be executed through the `public` symlink, through a real path
 * inside releases/<ts>/public, or from a release that was partially swapped —
 * none of which can be trusted to resolve __DIR__ the same way — so detection
 * is done from the nearest `.env` ancestor instead of matching on `artisan`.
 *
 * @return array{root: string, home: string, layout: 'legacy'|'release', error?: string}
 */
function rd_find_root(): array
{
    $dir = __DIR__;

    while (true) {
        if (is_file($dir.'/.env')) {
            $envDir = $dir;
            break;
        }

        $parent = dirname($dir);
        if ($parent === $dir) {
            return ['root' => '', 'home' => '', 'layout' => 'legacy', 'error' => 'No .env ancestor found from '.__DIR__];
        }

        $dir = $parent;
    }

    // Case A: nearest .env sits inside a release dir (<home>/releases/<ts>).
    $parentOfEnv = dirname($envDir);
    if (basename($parentOfEnv) === 'releases' && is_dir($parentOfEnv)) {
        return ['root' => $envDir, 'home' => dirname($parentOfEnv), 'layout' => 'release'];
    }

    // Case B: nearest .env is the home dir of a release layout (webroot flipped
    // to the `public` symlink, artisan only exists inside releases).
    if (is_link($envDir.'/current')) {
        $resolved = rd_resolve_symlink($envDir.'/current', $envDir);
        $root = $resolved !== '' && is_dir($resolved) ? (realpath($resolved) ?: $resolved) : $envDir;

        return ['root' => $root, 'home' => $envDir, 'layout' => 'release'];
    }

    // Case C: plain legacy in-place app (@__DIR__ started above the webroot).
    return ['root' => $envDir, 'home' => $envDir, 'layout' => 'legacy'];
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
 * Resolve a symlink target relative to its home dir.
 */
function rd_resolve_symlink(string $link, string $home): string
{
    if (! is_link($link)) {
        return '';
    }

    $target = (string) readlink($link);

    if ($target === '') {
        return '';
    }

    if (str_starts_with($target, '/')) {
        return $target;
    }

    return $home.'/'.$target;
}

/**
 * List release dirs under <home>/releases, newest first. Hidden staging dirs
 * are skipped.
 *
 * @return array<int, string> basenames of releases, newest first
 */
function rd_list_releases(string $home): array
{
    $byMtime = [];

    foreach (glob($home.'/releases/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $name = basename($dir);
        if (str_starts_with($name, '.')) {
            continue;
        }

        $byMtime[$name] = (int) @filemtime($dir);
    }

    arsort($byMtime);

    return array_keys($byMtime);
}

/**
 * Resolve the active release dir (via the `current` symlink, falling back to
 * the newest release when the symlink is missing or stale).
 */
function rd_active_release(string $home): string
{
    $resolved = rd_resolve_symlink($home.'/current', $home);

    if ($resolved !== '' && is_dir($resolved)) {
        return rtrim($resolved, '/\\');
    }

    $list = rd_list_releases($home);

    return $list === [] ? '' : $home.'/releases/'.$list[0];
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
/**
 * Layout from a topo array — release when root != home, else legacy. Absent
 * key (unit-test topo or older callers) defaults to legacy.
 */
function rd_layout_of(array $topo): string
{
    return (string) ($topo['layout'] ?? ($topo['root'] !== $topo['home'] ? 'release' : 'legacy'));
}

/**
 * @return array<string, mixed>
 */
function rd_action_status(array $topo): array
{
    $root = $topo['root'];
    $home = $topo['home'];
    $layout = rd_layout_of($topo);
    $output = [];
    $vendorAutoload = is_file($root.'/vendor/autoload.php');
    $versionFile = $root.'/.deployed-version';
    $version = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '';
    $deployZip = $home.'/deploy.zip';
    $stranded = rd_find_stranded_backup($root);
    $current = rd_active_release($home);

    if ($layout === 'release' && $current !== '' && is_file($current.'/.deployed-version')) {
        $version = trim((string) file_get_contents($current.'/.deployed-version'));
    }

    $releases = [];
    $releasesDir = $home.'/releases';

    foreach (rd_list_releases($home) as $name) {
        $release = $releasesDir.'/'.$name;
        $releases[] = [
            'name' => $name,
            'version' => is_file($release.'/.deployed-version')
                ? trim((string) file_get_contents($release.'/.deployed-version'))
                : '',
            'active' => $current !== '' && rtrim($current, '/\\') === rtrim($release, '/\\'),
        ];
    }

    $currentSymlink = rd_resolve_symlink($home.'/current', $home);
    $currentSymlink = $currentSymlink === '' ? '' : (str_starts_with($currentSymlink, $home) ? substr($currentSymlink, strlen($home) + 1) : $currentSymlink);

    $output[] = 'Layout: '.$layout;
    $output[] = 'Root: '.$root;
    $output[] = 'Home: '.$home;
    $output[] = $vendorAutoload ? 'vendor/autoload.php OK' : 'vendor/autoload.php MISSING';
    $output[] = $stranded !== '' ? 'Stranded vendor backup: '.$stranded : 'No stranded vendor backup';

    if ($layout === 'release') {
        $publicLink = $home.'/public';
        $webTarget = is_link($publicLink) ? rd_resolve_symlink($publicLink, $home) : '';
        $output[] = 'Webroot public/ -> '.($webTarget !== '' ? $webTarget : (is_dir($publicLink) ? '(real dir)' : 'MISSING'));
    }

    return [
        'ok' => true,
        'action' => 'status',
        'layout' => $layout,
        'root' => $root,
        'home' => $home,
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
        'webroot' => is_link($home.'/public') ? rd_resolve_symlink($home.'/public', $home) : (is_dir($home.'/public') ? 'public/' : ''),
        'output' => $output,
    ];
}

/**
 * @return array<string, mixed>
 */
function rd_action_recover(array $topo): array
{
    $root = $topo['root'];
    $home = $topo['home'];
    $layout = rd_layout_of($topo);
    $output = [];

    if ($layout === 'release') {
        return rd_recover_release_layout($topo, $output);
    }

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
 * Repair a release layout: ensure `current` + webroot `public` symlinks point
 * at a healthy release, preferring the one that holds a live vendor/.
 *
 * @param  array{root: string, home: string, layout: string}  $topo
 * @return array<string, mixed>
 */
function rd_recover_release_layout(array $topo, array &$output): array
{
    $home = $topo['home'];
    $list = rd_list_releases($home);

    if ($list === []) {
        $output[] = 'No releases present; nothing to heal in release mode.';

        return ['ok' => true, 'action' => 'recover', 'status' => 'nothing_to_do', 'output' => $output];
    }

    // Best release = newest one with a live vendor/autoload.php.
    $best = '';
    foreach ($list as $name) {
        $release = $home.'/releases/'.$name;
        if (is_file($release.'/vendor/autoload.php') && is_file($release.'/artisan')) {
            $best = $release;
            break;
        }
    }

    if ($best === '') {
        $output[] = 'No release has a healthy vendor/ — run release-install from deploy.zip.';

        return ['ok' => true, 'action' => 'recover', 'status' => 'no_healthy_release', 'output' => $output];
    }

    $changes = [];
    $current = rd_active_release($home);

    if (rtrim($current, '/\\') !== rtrim($best, '/\\')) {
        $changes[] = 'current -> '.basename($best);
    }

    if (! @symlink('releases/'.basename($best), $home.'/current')) {
        $currentIsStale = is_link($home.'/current') || file_exists($home.'/current');
        if ($currentIsStale) {
            @unlink($home.'/current');
        }
        if (! @symlink('releases/'.basename($best), $home.'/current')) {
            $output[] = 'Failed to re-point current/ symlink.';

            return ['ok' => false, 'action' => 'recover', 'status' => 'symlink_failed', 'output' => $output];
        }
    }

    $publicLink = $home.'/public';
    $publicTarget = is_link($publicLink) ? rd_resolve_symlink($publicLink, $home) : '';
    $desiredPublic = 'releases/'.basename($best).'/public';

    // If public/ is a real dir here the layout half-migrated; move it aside.
    if (is_dir($publicLink) && ! is_link($publicLink)) {
        $aside = $home.'/public.legacy-'.basename($best);
        if (! @rename($publicLink, $aside)) {
            $output[] = 'Failed to move legacy public/ aside for re-point.';

            return ['ok' => false, 'action' => 'recover', 'status' => 'public_move_failed', 'output' => $output];
        }
        $output[] = 'Moved legacy public/ aside: '.basename($aside);
        $publicTarget = '';
    }

    if ($publicTarget !== $desiredPublic || ! is_link($publicLink)) {
        $changes[] = 'public -> '.$desiredPublic;
    }

    $tmpPublic = $home.'/.public.'.uniqid('', true);

    if (@symlink('releases/'.basename($best).'/public', $tmpPublic)) {
        @rename($tmpPublic, $publicLink);
    } else {
        $output[] = 'Failed to re-point public/ symlink.';

        return ['ok' => false, 'action' => 'recover', 'status' => 'public_symlink_failed', 'output' => $output];
    }

    $output[] = 'Release layout healed: '.implode('; ', $changes ?: ['no changes needed']);

    return ['ok' => true, 'action' => 'recover', 'status' => 'release_healed', 'output' => $output];
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
 * --- Release-model actions (Capistrano-style current -> releases/<ts>) ---
 */

/**
 * Sanitize a version string into a filesystem-safe release tag.
 */
function rd_release_tag(string $version): string
{
    $tag = preg_replace('/[^A-Za-z0-9._-]+/', '-', $version) ?? 'unknown';
    $tag = preg_replace('/-{2,}/', '-', $tag);
    $tag = trim($tag, '-._ ');

    return $tag === '' ? 'unknown' : $tag;
}

/**
 * Budget-aware, resumable ZipArchive extraction for the release staging dir.
 * Writes a `.progress` marker so a request killed by the host connection
 * timeout simply resumes on the next invoke against the same zip.
 *
 * @return array{complete: bool, index: int, total: int, error?: string}
 */
function rd_extract_release_budgeted(string $zipPath, string $stageDir, float $budgetUntil): array
{
    $progress = $stageDir.'.progress';
    $start = is_file($progress) ? (int) (string) file_get_contents($progress) : 0;

    $zip = new ZipArchive;

    if ($zip->open($zipPath) !== true) {
        return ['complete' => false, 'index' => $start, 'total' => 0, 'error' => 'zip-open-failed'];
    }

    $total = $zip->numFiles;
    $i = $start;

    while ($i < $total && microtime(true) < $budgetUntil) {
        $name = $zip->getNameIndex($i);
        $name = trim((string) $name);

        if ($name === '' || str_contains($name, '..')) {
            $i++;

            continue;
        }

        $zip->extractTo($stageDir, [$name]);
        $i++;
        file_put_contents($progress, (string) $i, LOCK_EX);
    }

    $complete = $i >= $total;
    $zip->close();

    if ($complete) {
        @unlink($progress);
    }

    return ['complete' => $complete, 'index' => $i, 'total' => $total];
}

/**
 * Symlink home/shared .env + storage into a staged release, so every release
 * shares the same env file, uploads, logs and session/framework caches.
 */
function rd_link_release_shared(string $stage, string $home, array &$output): void
{
    $sharedEnv = $home.'/.env';

    if (is_file($sharedEnv)) {
        if (file_exists($stage.'/.env')) {
            @unlink($stage.'/.env');
        }
        if (! @symlink($sharedEnv, $stage.'/.env')) {
            $output[] = 'Warning: could not symlink shared .env into release.';
        }
    }

    if (is_dir($home.'/storage')) {
        if (file_exists($stage.'/storage')) {
            rd_rrmdir($stage.'/storage');
        }
        if (! @symlink($home.'/storage', $stage.'/storage')) {
            $output[] = 'Warning: could not symlink shared storage into release.';
        }
    }
}

/**
 * Install home/deploy.zip as an immutable release and atomically re-point
 * `current` + the webroot `public` symlink at it.
 *
 * First run against a legacy layout migrates to the release model: the old
 * in-place `public/` dir is moved aside (kept for rollback) and replaced by a
 * symlink. Subsequent runs are zero-downtime symlink flips.
 *
 * @return array<string, mixed>
 */
function rd_action_release_install(array $topo, string $version = ''): array
{
    $home = $topo['home'];
    $layout = rd_layout_of($topo);
    $output = [];
    $zipPath = $home.'/deploy.zip';

    if (! is_file($zipPath)) {
        return [
            'ok' => false,
            'action' => 'release-install',
            'status' => 'no_deploy_zip',
            'output' => ['No '.$home.'/deploy.zip to install.'],
        ];
    }

    if (! is_dir($home.'/releases') && ! @mkdir($home.'/releases', 0755, true)) {
        return ['ok' => false, 'action' => 'release-install', 'status' => 'releases_mkdir_failed', 'output' => ['Could not create '.$home.'/releases.']];
    }

    $budgetUntil = microtime(true) + 85;

    $zipSize = (int) @filesize($zipPath) ?: 0;
    $zipMtime = (int) @filemtime($zipPath) ?: 0;
    $stageName = 'staging-'.substr(hash('sha256', $zipSize.'-'.$zipMtime), 0, 14);
    $stageDir = $home.'/releases/.'.$stageName;

    if (! is_dir($stageDir) && ! @mkdir($stageDir, 0755, true)) {
        return ['ok' => false, 'action' => 'release-install', 'status' => 'stage_mkdir_failed', 'output' => ['Could not create '.$stageDir.'.']];
    }

    $extracted = rd_extract_release_budgeted($zipPath, $stageDir, $budgetUntil);

    if (isset($extracted['error'])) {
        return ['ok' => false, 'action' => 'release-install', 'status' => 'bad_zip', 'output' => ['Failed to open deploy.zip ('.$extracted['error'].').']];
    }

    $output[] = "Extracted {$extracted['index']}/{$extracted['total']} zip entries".($extracted['complete'] ? '' : ' — time budget hit, resume release-install to continue');

    if (! $extracted['complete']) {
        return [
            'ok' => true,
            'action' => 'release-install',
            'status' => 'extracting',
            'partial' => true,
            'extracted' => $extracted['index'],
            'total' => $extracted['total'],
            'output' => $output,
        ];
    }

    if (! is_file($stageDir.'/vendor/autoload.php') || ! is_file($stageDir.'/artisan') || ! is_file($stageDir.'/bootstrap/app.php')) {
        rd_rrmdir($stageDir);
        $output[] = 'Extracted tree missing vendor/autoload.php, artisan or bootstrap/app.php';

        return ['ok' => false, 'action' => 'release-install', 'status' => 'extract_failed', 'output' => $output];
    }

    rd_link_release_shared($stageDir, $home, $output);

    // Self-host the runner inside the release's public so the webroot keeps
    // answering /runner.php after the flip even if the app itself is broken.
    if (is_dir($stageDir.'/public')) {
        if (! @copy(__FILE__, $stageDir.'/public/'.basename(__FILE__))) {
            $output[] = 'Warning: could not self-copy runner.php into new release public/';
        }
    }

    if ($version !== '') {
        file_put_contents($stageDir.'/.deployed-version', $version);
    }

    // Public storage symlink so uploaded media stays readable via /storage/.
    if (! file_exists($stageDir.'/public/storage') && is_dir($home.'/storage/app/public')) {
        if (! @symlink($home.'/storage/app/public', $stageDir.'/public/storage')) {
            $output[] = 'Warning: could not symlink public/storage.';
        }
    }

    $ts = gmdate('Y-m-d\TH-i-s\Z');
    $releaseName = $ts.'-'.rd_release_tag($version);
    $releaseDir = $home.'/releases/'.$releaseName;

    if (is_dir($releaseDir)) {
        rd_rrmdir($releaseDir);
    }

    if (! @rename($stageDir, $releaseDir)) {
        $output[] = 'Could not finalize release dir: '.$releaseName;

        return ['ok' => false, 'action' => 'release-install', 'status' => 'finalize_failed', 'output' => $output];
    }

    // --- Flip `public` webroot symlink ---
    if ($layout === 'release') {
        $tmp = $home.'/.public.'.uniqid('', true);
        $flipOk = @symlink('releases/'.$releaseName.'/public', $tmp) && @rename($tmp, $home.'/public');
        if (! $flipOk) {
            $output[] = 'Could not re-point webroot public/ symlink.';

            return ['ok' => false, 'action' => 'release-install', 'status' => 'public_flip_failed', 'output' => $output];
        }
    } else {
        // Legacy -> release migration: move the real public/ aside, then symlink.
        $legacyDir = $home.'/public.legacy-'.$ts;
        if (is_dir($home.'/public') && ! is_link($home.'/public') && ! @rename($home.'/public', $legacyDir)) {
            $output[] = 'Could not move legacy public/ aside; release not activated ('.basename($legacyDir).').';
            @rename($releaseDir, $stageDir);

            return ['ok' => false, 'action' => 'release-install', 'status' => 'public_move_failed', 'output' => $output];
        }

        if (is_link($home.'/public')) {
            @unlink($home.'/public');
        }

        if (! @symlink('releases/'.$releaseName.'/public', $home.'/public')) {
            // Restore the legacy dir we just moved aside.
            if (isset($legacyDir) && is_dir($legacyDir)) {
                @rename($legacyDir, $home.'/public');
            }
            $output[] = 'Could not create webroot public/ symlink; legacy webroot restored.';
            @rename($releaseDir, $stageDir);

            return ['ok' => false, 'action' => 'release-install', 'status' => 'public_symlink_failed', 'output' => $output];
        }

        $output[] = 'Migrated to release layout: legacy public/ kept as '.basename($legacyDir);
    }

    // --- Flip `current` pointer last (metadata only; webroot is source of truth) ---
    $releasesDir = $home.'/releases';
    $tmpCurrent = $home.'/.current.'.uniqid('', true);

    if (@symlink('releases/'.$releaseName, $tmpCurrent) && @rename($tmpCurrent, $home.'/current')) {
        $output[] = 'current -> releases/'.$releaseName;
    } else {
        $output[] = 'Release activated but could not write current/ symlink.';
    }

    @unlink($zipPath);
    $output[] = 'Removed '.$zipPath.' (release installed).';

    file_put_contents($home.'/.deployed-version', $version);

    return ['ok' => true, 'action' => 'release-install', 'status' => 'installed', 'release' => $releaseName, 'output' => $output];
}

/**
 * Re-point `current` + webroot `public` at the previous release. With a single
 * release and a legacy backup present, the layout is rolled back to legacy
 * in-place (the real public/ dir is restored).
 *
 * @return array<string, mixed>
 */
function rd_action_release_rollback(array $topo): array
{
    $home = $topo['home'];
    $output = [];
    $list = rd_list_releases($home);
    $current = rd_active_release($home);

    if ($list === []) {
        return ['ok' => false, 'action' => 'release-rollback', 'status' => 'no_releases', 'output' => ['No releases to roll back.']];
    }

    $currentName = $current !== '' && str_starts_with($current, $home) ? substr($current, strlen($home) + 1) : basename($current);
    $currentName = str_starts_with($currentName, 'releases/') ? substr($currentName, strlen('releases/')) : $currentName;

    $target = '';

    // Prefer the release right before the active one in the newest-first list.
    foreach ($list as $i => $name) {
        if ($name === $currentName) {
            $target = $list[$i + 1] ?? '';
            break;
        }
    }

    // Current is unknown/stale -> roll back to the newest release.
    if ($target === '' && $currentName === '') {
        $target = $list[0];
    }

    if ($target === '') {
        // No previous release to go back to. If a legacy webroot backup exists,
        // restore the legacy in-place layout instead.
        $legacyCandidate = glob($home.'/public.legacy-*', GLOB_ONLYDIR) ?? [];
        if ($legacyCandidate !== []) {
            usort($legacyCandidate, fn (string $a, string $b): int => (int) @filemtime($b) <=> (int) @filemtime($a));
            $legacyDir = $legacyCandidate[0];

            if (is_link($home.'/public')) {
                @unlink($home.'/public');
            }
            if (@rename($legacyDir, $home.'/public')) {
                @unlink($home.'/current');
                $output[] = 'Rolled back to legacy in-place webroot ('.basename($legacyDir).' restored).';

                return ['ok' => true, 'action' => 'release-rollback', 'status' => 'rolled_back_to_legacy', 'output' => $output];
            }
        }

        $output[] = 'No previous release and no legacy webroot backup to restore.';

        return ['ok' => false, 'action' => 'release-rollback', 'status' => 'nothing_to_rollback_to', 'output' => $output];
    }

    $tmp = $home.'/.public.'.uniqid('', true);

    if (! @symlink('releases/'.$target.'/public', $tmp) || ! @rename($tmp, $home.'/public')) {
        return ['ok' => false, 'action' => 'release-rollback', 'status' => 'public_flip_failed', 'output' => ['Could not re-point webroot public/ symlink to releases/'.$target.'.']];
    }

    $tmpCurrent = $home.'/.current.'.uniqid('', true);

    if (@symlink('releases/'.$target, $tmpCurrent) && @rename($tmpCurrent, $home.'/current')) {
        $output[] = 'current -> releases/'.$target;
    } else {
        $output[] = 'Webroot flipped but could not update current/ symlink.';
    }

    $versionFile = $home.'/releases/'.$target.'/.deployed-version';
    file_put_contents($home.'/.deployed-version', is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '');

    return ['ok' => true, 'action' => 'release-rollback', 'status' => 'rolled_back', 'release' => $target, 'output' => $output];
}

/**
 * Hold back the newest N releases and delete older ones (budget-aware, partial
 * when the host connection budget runs out). Also clears stale staging dirs
 * and orphaned .progress markers older than one day.
 *
 * @return array<string, mixed>
 */
function rd_action_release_cleanup(array $topo, int $keep = 3): array
{
    $home = $topo['home'];
    $output = [];
    $keep = max(1, $keep);
    $list = rd_list_releases($home);
    $toDelete = array_slice($list, $keep);
    $budgetUntil = microtime(true) + 85;
    $removed = 0;
    $partial = false;

    foreach ($toDelete as $name) {
        if (microtime(true) > $budgetUntil) {
            $partial = true;
            break;
        }

        $path = $home.'/releases/'.$name;
        $version = is_file($path.'/.deployed-version') ? trim((string) file_get_contents($path.'/.deployed-version')) : '';
        $output[] = 'Deleting old release '.$name.($version !== '' ? ' ('.$version.')' : '').'...';
        rd_rrmdir($path);
        $removed++;
        $output[] = 'Deleted '.$name;
    }

    // Stale staging dirs + progress markers older than a day.
    $cutoff = time() - 86400;

    foreach (glob($home.'/releases/.*staging*', GLOB_ONLYDIR) ?: [] as $stale) {
        if ((int) @filemtime($stale) < $cutoff && rd_rrmdir($stale)) {
            $output[] = 'Cleared stale staging dir '.basename($stale);
            @unlink($stale.'.progress');
        }
    }

    if ($removed === 0 && ! $partial) {
        $output[] = 'No releases older than the keep-'.$keep.' window.';
    }

    return [
        'ok' => true,
        'action' => 'release-cleanup',
        'status' => $partial ? 'partial' : 'ok',
        'keep' => $keep,
        'removed' => $removed,
        'partial' => $partial,
        'remaining' => count($toDelete) - $removed,
        'output' => $output,
    ];
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

    // Budget: stay under the host's HTTP connection timeout, NOT PHP's
    // max_execution_time. Shared hosts kill long HTTP requests at the web
    // server layer (LiteSpeed/Apache "Connection Timeout", observed ~120s on
    // tunispromo.tn) even when set_time_limit is lifted to 600s — a request
    // that outlives the connection dies mid-walk and returns no JSON at all.
    // So the budget is capped at 85s by default (safely under the observed
    // ~120s limit). Parts cut off by the budget return a partial success so a
    // repeat invoke (prune is idempotent) continues the cleanup.
    if ($budgetSeconds === null) {
        $budgetSeconds = 85;
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
                    'release-install' => rd_action_release_install($topo, (string) ($_GET['version'] ?? $_POST['version'] ?? '')),
                    'release-rollback' => rd_action_release_rollback($topo),
                    'release-cleanup' => rd_action_release_cleanup($topo, (int) ($_GET['keep'] ?? $_POST['keep'] ?? 3)),
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
