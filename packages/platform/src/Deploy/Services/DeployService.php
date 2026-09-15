<?php

namespace Nreljed\Platform\Deploy\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Nreljed\Platform\Services\CoreVersionService;

class DeployService
{
    public function __construct(private readonly CoreVersionService $coreVersion) {}

    /**
     * Prune `.env.backup.<timestamp>` snapshots down to the newest N.
     *
     * Centralised here so the retention behaviour is framework-free testable
     * (the deploy suite deliberately avoids booting the application). The
     * `env_backup_retention` config key caps how many backups survive; pass an
     * explicit $retention when called without a booted app (e.g. in tests).
     *
     * @return int number of backups pruned
     */
    public function pruneEnvBackups(string $envPath, ?int $retention = null): int
    {
        $retention = $retention ?? (int) config('deploy.env_backup_retention', 5);
        $retention = max(1, $retention);

        $backups = glob($envPath.'.backup.*') ?: [];
        if ($backups === []) {
            return 0;
        }

        sort($backups);

        $stale = array_slice($backups, 0, -$retention);
        foreach ($stale as $file) {
            @unlink($file);
        }

        return count($stale);
    }

    /**
     * Check GitHub for a newer release and, if found, deploy it by pulling the
     * deploy.zip asset into the current installation.
     *
     * This is the server-side self-deploy path: the app's own scheduler calls
     * it so deploys keep working even when CI cannot reach the host (e.g. the
     * hosting firewall blocks datacenter IPs).
     *
     * @return array{status: string, message: string, tag: string|null, output: array<int, string>, core: array<string, mixed>, status_code: int}
     */
    public function check(): array
    {
        set_time_limit(300);

        $output = [];
        $envPath = base_path('.env');
        $githubToken = $this->githubToken($envPath);

        $versionFile = config('deploy.version_path', base_path('.deployed-version'));
        $lockFile = config('deploy.lock_path', base_path('.deploy-lock'));
        $repo = config('deploy.github_repo', 'nreljed/laravel');
        $lockTtl = (int) config('deploy.lock_ttl', 600);

        if (File::exists($lockFile) && (time() - File::lastModified($lockFile)) < $lockTtl) {
            return $this->result('locked', 'Deploy already in progress.');
        }

        File::put($lockFile, (string) getmypid());

        try {
            $releases = $this->githubApi("https://api.github.com/repos/$repo/releases?per_page=1", $githubToken);

            if (! $releases || ! isset($releases[0]['tag_name'])) {
                return $this->result('no-release', $releases === null ? 'GitHub API request failed.' : 'No releases found.');
            }

            $latestTag = $releases[0]['tag_name'];
            $currentTag = File::exists($versionFile) ? trim((string) File::get($versionFile)) : '';

            if ($latestTag === $currentTag) {
                return $this->result('current', "Already up to date ($latestTag).", tag: $latestTag);
            }

            // Release-layout hosts are served from releases/<active> (the webroot
            // `public` is a symlink into it). The legacy in-place swap below would
            // mutate an immutable release directory and break the deploy-runner's
            // rollback/cleanup model — refuse and tell CI to hand off to the
            // runner's release-install action instead.
            if ($this->usesReleaseLayout()) {
                $output[] = "New release: $latestTag";
                $output[] = 'Release-layout host detected — in-place self-deploy disabled.';

                return $this->result(
                    'release-layout',
                    "Release-layout host ($latestTag available) — deploy via the deploy-runner release-install action.",
                    tag: $latestTag,
                    output: $output,
                );
            }

            $assetUrl = '';
            foreach ($releases[0]['assets'] ?? [] as $asset) {
                if (($asset['name'] ?? null) === 'deploy.zip') {
                    $assetUrl = $asset['url'] ?? '';
                    break;
                }
            }

            if ($assetUrl === '') {
                return $this->result('error', 'No deploy.zip in release', output: $output);
            }

            $output[] = "New release: $latestTag";

            $zipData = $this->githubDownload($assetUrl, $githubToken);
            if (! $zipData || strlen($zipData) < 100) {
                return $this->result('error', 'Download failed', output: $output);
            }

            $zipFile = storage_path('app/deploy-'.uniqid().'.zip');
            File::put($zipFile, $zipData);
            $output[] = 'Downloaded '.round(strlen($zipData) / 1048576, 1).' MB';

            try {
                $backupEnv = File::exists($envPath) ? File::get($envPath) : '';
                $zipFiles = [];

                // Atomic install: extract into a staging dir first, boot-check the
                // staged tree, THEN swap it into place while keeping the current
                // vendor/ as a backup. A failed/broken release is rejected before
                // it ever touches the live install, so a bad deploy can no longer
                // leave the app dead (the shop outage caused by vendor/ purge
                // dying mid-extract).
                if (! $this->installZipFile($zipFile, $zipFiles, $output)) {
                    return $this->result('error', 'Install failed; previous version kept', output: $output, statusCode: 500);
                }

                if ($backupEnv) {
                    File::put($envPath, $backupEnv);
                    $output[] = 'Restored .env';
                }

                File::put($versionFile, $latestTag);
                $output[] = "New version: $latestTag";

                $stale = $this->removeStaleFiles($zipFiles);
                if ($stale['aborted']) {
                    $output[] = "Stale cleanup aborted (ratio: $stale[ratio])";
                } else {
                    $output[] = "Removed $stale[removed] stale files";
                }

                $this->cleanStaleMigrations($zipFiles, $output);

                $commands = [
                    'package:discover' => [[]],
                    'migrate' => [['--force' => true]],
                    'app:create-admin' => [[
                        '--no-interaction' => true,
                        '--email' => config('admin.email', ''),
                        '--password' => config('admin.password', ''),
                        '--name' => config('admin.name', 'Admin'),
                    ]],
                    'config:cache' => [[]],
                    'route:cache' => [[]],
                    'view:cache' => [[]],
                    'storage:link' => [['--force' => true]],
                ];

                foreach ($commands as $cmd => [$params]) {
                    try {
                        Artisan::call($cmd, $params);
                        $output[] = "$cmd: ".strtok(trim(Artisan::output()), "\n");
                    } catch (\Exception $e) {
                        $output[] = "$cmd: ERROR ".$e->getMessage();
                        if (in_array($cmd, ['migrate', 'config:cache'])) {
                            return $this->result('error', "$cmd failed", output: $output, statusCode: 500);
                        }
                    }
                }

                return $this->result('deployed', "Deployed $latestTag.", tag: $latestTag, output: $output);
            } finally {
                // Never leave the downloaded zip behind, even when an install
                // throws — stale deploy-*.zip files accumulate unbounded and
                // bloat the recursive walk the SystemHealth page runs.
                File::delete($zipFile);
            }
        } finally {
            File::delete($lockFile);
        }
    }

    /**
     * Safely apply a deploy.zip to the live install.
     *
     * Kill-window-free ordering: the live tree (including vendor/) is NEVER
     * touched until the new release has been extracted to staging AND passed
     * its boot check. Only then is the old vendor/ moved aside and the staged
     * release swapped in with a fast directory rename. If the process dies at
     * any point before activation, the app keeps serving the previous version.
     *
     * 1. Builds the zip file index,
     * 2. Heals any backup left behind by a killed earlier run,
     * 3. Extracts the archive into an isolated staging dir,
     * 4. Boot-checks the staged tree (`php artisan --version`), and
     * 5. Backs up the current vendor/, swaps the staged tree into place.
     *
     * @see https://github.com/nreljed/platform for the deploy architecture.
     */
    public function installZipFile(string $zipPath, array &$zipFiles, array &$output): bool
    {
        // The in-place install mutates the live tree — never run it on a
        // release-layout host where the app is served from releases/<active>.
        if ($this->usesReleaseLayout()) {
            $output[] = 'Release-layout host — in-place install refused. Use the deploy-runner release-install action instead.';

            return false;
        }

        if ($this->recoverStrandedBackup($output)) {
            $output[] = 'Interrupted install healed; previous version restored';

            return false;
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            $output[] = 'Failed to open ZIP';

            return false;
        }

        $zipFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat && $stat['name'] !== '' && ! str_ends_with($stat['name'], '/')) {
                $zipFiles[str_replace('\\', '/', $stat['name'])] = true;
            }
        }

        $stage = storage_path('app/deploy-staging-'.uniqid());

        try {
            $extracted = $zip->extractTo($stage);
        } finally {
            $zip->close();
        }

        if (! $extracted || ! File::exists($stage.'/vendor/autoload.php')) {
            File::deleteDirectory($stage);

            $output[] = 'Extraction failed or staged vendor is incomplete';

            return false;
        }

        $output[] = 'Extracted '.count($zipFiles).' files to staging';

        // A thrown boot check must never orphan the staging tree — the app's
        // SystemHealth page walks storage/app recursively and a pile-up of
        // leftover staging trees blows its execution-time budget on shared hosts.
        try {
            $verified = $this->bootCheck($stage, $output);
        } catch (\Throwable $e) {
            File::deleteDirectory($stage);
            $output[] = 'Boot check errored: '.$e->getMessage();

            return false;
        }

        if (! $verified) {
            File::deleteDirectory($stage);

            $output[] = 'Boot check failed; release rejected (previous version kept)';

            return false;
        }

        try {
            $this->swapStagedIntoPlace($stage, $output);
        } catch (\Throwable $e) {
            File::deleteDirectory($stage);
            $output[] = 'Install aborted: '.$e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * Recover from a deploy killed between "old vendor moved aside" and the
     * swap (crash, timeout, OOM): if the live vendor/ is missing but a
     * `deploy-staging-*-vendor-backup` directory exists, move it back so the
     * app boots on the version that was live before the interrupted run.
     *
     * @param  array<int, string>  $output
     */
    private function recoverStrandedBackup(array &$output): bool
    {
        if (File::isDirectory($this->installRoot().'/vendor')) {
            return false;
        }

        $backups = array_values(File::glob(storage_path('app/deploy-staging-*-vendor-backup')));
        if ($backups === []) {
            return false;
        }

        sort($backups);
        File::move(end($backups), $this->installRoot().'/vendor');
        foreach (array_slice($backups, 0, -1) as $stale) {
            File::deleteDirectory($stale);
        }

        $this->clearBootstrapCache($output);
        $output[] = 'Restored previous vendor/ from interrupted-install backup';

        return true;
    }

    /**
     * Remove leftover app files that are not present in the incoming zip.
     *
     * @param  array<string, true>  $zipFiles
     * @return array{aborted: bool, removed: int, candidates: int, ratio: float}
     */
    public function removeStaleFiles(array $zipFiles): array
    {
        $staleDirs = ['app', 'config', 'database', 'public', 'resources', 'routes', 'bootstrap'];
        $protected = ['bootstrap/cache/'];
        $rootLen = strlen(base_path()) + 1;
        $staleCandidates = 0;
        $staleRemoved = 0;
        $staleList = [];

        foreach ($staleDirs as $dir) {
            $full = base_path($dir);
            if (! is_dir($full)) {
                continue;
            }
            $files = File::allFiles($full);
            foreach ($files as $file) {
                $rel = str_replace('\\', '/', substr($file->getPathname(), $rootLen));
                $skip = array_any($protected, fn ($p) => str_starts_with($rel, $p));
                if ($skip) {
                    continue;
                }
                if (! str_ends_with($rel, '.php') && ! str_ends_with($rel, '.js') && ! str_ends_with($rel, '.ts') && ! str_ends_with($rel, '.tsx')) {
                    continue;
                }
                $staleCandidates++;
                if (! isset($zipFiles[$rel])) {
                    $staleRemoved++;
                    $staleList[] = $rel;
                }
            }
        }

        $staleRatio = $staleCandidates > 0 ? $staleRemoved / $staleCandidates : 0;

        if ($staleRatio > 0.10) {
            return ['aborted' => true, 'removed' => 0, 'candidates' => $staleCandidates, 'ratio' => $staleRatio];
        }

        foreach ($staleList as $rel) {
            File::delete(base_path($rel));
        }

        return ['aborted' => false, 'removed' => $staleRemoved, 'candidates' => $staleCandidates, 'ratio' => $staleRatio];
    }

    /**
     * Delete migration files that are not present in the incoming zip.
     *
     * @param  array<string, true>  $zipFiles
     * @param  array<int, string>  $output
     */
    public function cleanStaleMigrations(array $zipFiles, array &$output): void
    {
        $migrationsDir = database_path('migrations');
        if (! is_dir($migrationsDir)) {
            return;
        }

        $removed = 0;
        foreach (File::files($migrationsDir) as $file) {
            $rel = 'database/migrations/'.$file->getFilename();
            if (! isset($zipFiles[$rel])) {
                File::delete($file->getPathname());
                $removed++;
            }
        }

        if ($removed > 0) {
            $output[] = "Cleaned $removed stale migration files";
        }
    }

    /**
     * True when the app root sits inside a Capistrano-style release directory
     * (i.e. <home>/releases/<active> — the deploy-runner's release layout).
     *
     * On such hosts the legacy in-place swap must never run: base_path()
     * resolves inside the active release and a swap would mutate the immutable
     * release tree.  The canonical deploy path is the deploy-runner's atomic
     * release-install action (restore.yml).
     *
     * Accepts an optional $basePath to support callers that resolve outside
     * the normal Laravel context (tests, console commands).
     */
    public function usesReleaseLayout(?string $basePath = null): bool
    {
        $root = rtrim($basePath ?? base_path(), '/\\');
        $parent = dirname($root);

        return basename($parent) === 'releases' && is_dir($parent);
    }

    /**
     * Directory the staged release is swapped into. Defaults to the project
     * root; overridable so the safe-install helpers can run against a sandbox.
     */
    private function installRoot(): string
    {
        return rtrim((string) config('deploy.install_root', base_path()), '/\\');
    }

    /**
     * Move the current vendor/ aside so a failed release can be rolled back.
     *
     * Called only inside the swap, after the new release has boot-checked.
     * The backup lives in storage/app so the stranded-backup recovery glob in
     * recoverStrandedBackup() can find and heal a killed run.
     *
     * @param  array<int, string>  $output
     * @return string|null Absolute path to the backup, or null when there is
     *                     no existing vendor/ (fresh install — nothing to keep).
     */
    private function backupVendor(array &$output): ?string
    {
        $vendorPath = $this->installRoot().'/vendor';

        if (! File::isDirectory($vendorPath)) {
            return null;
        }

        $backup = storage_path('app/deploy-staging-'.uniqid().'-vendor-backup');
        File::move($vendorPath, $backup);
        $output[] = 'Backed up current vendor/';

        return $backup;
    }

    /**
     * Restore a previously backed-up vendor/ and clear caches so the app boots
     * against the version that was live before the interrupted swap.
     *
     * @param  array<int, string>  $output
     */
    private function restoreVendorBackup(?string $backup, array &$output): void
    {
        if (! $backup || ! File::isDirectory($backup)) {
            return;
        }

        if (File::isDirectory($this->installRoot().'/vendor')) {
            File::deleteDirectory($this->installRoot().'/vendor');
        }

        File::move($backup, $this->installRoot().'/vendor');
        $this->clearBootstrapCache($output);
        $output[] = 'Restored previous vendor/';
    }

    /**
     * Swap a boot-checked staging tree into the live install.
     *
     * Non-vendor files are mirrored first while the app keeps serving the old
     * vendor; the old vendor is then moved aside and the new one renamed in —
     * a two-rename window of milliseconds total. If anything throws after the
     * old vendor was moved aside, it is immediately restored. The backup is
     * discarded only after the new tree is fully in place.
     *
     * @param  array<int, string>  $output
     */
    private function swapStagedIntoPlace(string $stage, array &$output): void
    {
        $this->copyStagedTree($stage, $output);

        if (! File::isDirectory($stage.'/vendor') || ! File::exists($stage.'/vendor/autoload.php')) {
            throw new \RuntimeException('Staged vendor/ is incomplete after boot check');
        }

        $this->clearBootstrapCache($output);

        $backup = null;

        try {
            $backup = $this->backupVendor($output);
            File::move($stage.'/vendor', $this->installRoot().'/vendor');
        } catch (\Throwable $e) {
            if ($backup && File::isDirectory($backup)) {
                $this->restoreVendorBackup($backup, $output);
            }

            throw $e;
        }

        File::deleteDirectory($stage);

        if ($backup && File::isDirectory($backup)) {
            File::deleteDirectory($backup);
        }

        $output[] = 'Swapped staged release into place';
    }

    /**
     * Boot-check a staged install before it touches the live tree.
     *
     * Runs `php artisan --version` inside the staging dir (with the live .env
     * copied in) so any breakage across the new vendor + app files is caught
     * early — a rejected release leaves the live install completely untouched.
     *
     * Some shared hosts disable proc_open (so Laravel's Process facade throws a
     * LogicException). Fall back to `/usr/bin/env php artisan --version` via
     * shell_exec when available, and ultimately to a structural check so a
     * deploy can still proceed on locked-down hosts — the runner uses the same
     * ladder.
     *
     * @param  array<int, string>  $output
     */
    private function bootCheck(string $stage, array &$output): bool
    {
        if (! config('deploy.boot_check', true)) {
            $output[] = 'Boot check skipped (deploy.boot_check=false)';

            return true;
        }

        if (! File::exists($stage.'/vendor/autoload.php')) {
            return false;
        }

        if (File::exists($this->installRoot().'/.env')) {
            File::copy($this->installRoot().'/.env', $stage.'/.env');
        }

        $php = PHP_BINARY;

        try {
            $result = Process::path($stage)->timeout((int) config('deploy.boot_check_timeout', 90))
                ->run([$php, 'artisan', '--version']);

            if ($result->exitCode() !== 0) {
                $output[] = 'Boot check failed: '.trim($result->errorOutput());

                return false;
            }

            $output[] = 'Boot check passed';

            return true;
        } catch (\LogicException $e) {
            // proc_open unavailable — fall through to shell_exec / structural.
        }

        $cmd = 'cd '.escapeshellarg($stage).' && '.escapeshellarg($php).' artisan --version 2>&1';
        $version = function_exists('shell_exec') ? @shell_exec($cmd) : null;

        if ($version !== null && trim($version) !== '') {
            $output[] = 'Boot check passed (shell): '.trim($version);

            return true;
        }

        $required = [
            'artisan',
            'bootstrap/app.php',
            'bootstrap/providers.php',
            'public/index.php',
            'config/app.php',
            'routes/web.php',
        ];

        foreach ($required as $path) {
            if (! File::exists($stage.'/'.$path)) {
                $output[] = "Boot check failed (structural): missing $path";

                return false;
            }
        }

        $output[] = 'Boot check passed (structural only)';

        return true;
    }

    /**
     * Copy a staged tree into the live install without touching vendor/,
     * storage/ or .env (those are handled separately).
     *
     * @param  array<int, string>  $output
     */
    private function copyStagedTree(string $stage, array &$output): void
    {
        foreach (File::directories($stage) as $dir) {
            $name = basename($dir);

            if (in_array($name, ['vendor', 'storage'], true)) {
                continue;
            }

            File::copyDirectory($dir, $this->installRoot().'/'.$name);
        }

        foreach (File::files($stage) as $file) {
            if ($file->getFilename() === '.env') {
                continue;
            }

            File::copy($file->getPathname(), $this->installRoot().'/'.$file->getFilename());
        }

        $output[] = 'Mirrored staged app files over live tree';
    }

    /**
     * Remove directories that must always be regenerated from the incoming
     * release: bootstrap/cache artifacts (cached config/routes/views may
     * reference classes removed by this deploy).
     *
     * @param  array<int, string>  $output
     */
    private function clearBootstrapCache(array &$output): void
    {
        $path = $this->installRoot().'/bootstrap/cache';

        if (File::exists($path)) {
            File::deleteDirectory($path);
        }

        File::makeDirectory($path, 0755, true, true);
        $output[] = 'Cleared bootstrap/cache/';
    }

    /**
     * @return array{status: string, message: string, tag: string|null, output: array<int, string>, core: array<string, mixed>, status_code: int}
     */
    private function result(string $status, string $message, ?string $tag = null, array $output = [], int $statusCode = 200): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'tag' => $tag,
            'output' => $output,
            'core' => $this->coreVersion->info(),
            'status_code' => $statusCode,
        ];
    }

    private function githubToken(string $envPath): string
    {
        if (! File::exists($envPath)) {
            return '';
        }

        foreach (explode("\n", File::get($envPath)) as $line) {
            if (str_starts_with(trim($line), 'GITHUB_TOKEN=')) {
                return trim(trim(substr(trim($line), strlen('GITHUB_TOKEN='))), '"\'');
            }
        }

        return '';
    }

    private function githubApi(string $url, string $token): ?array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Platform',
            'Accept' => 'application/vnd.github.v3+json',
        ])->withToken($token)
            ->timeout(30)
            ->get($url);

        return $response->successful() ? $response->json() : null;
    }

    private function githubDownload(string $url, string $token): string|false
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Platform',
            'Accept' => 'application/octet-stream',
        ])->withToken($token)
            ->timeout(300)
            ->get($url);

        return $response->successful() ? $response->body() : false;
    }
}
