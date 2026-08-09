<?php

namespace Nreljed\DashboardCore\Deploy\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Nreljed\DashboardCore\Services\CoreVersionService;

class DeployService
{
    public function __construct(private readonly CoreVersionService $coreVersion) {}

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

            $backupEnv = File::exists($envPath) ? File::get($envPath) : '';

            $zip = new \ZipArchive;
            if ($zip->open($zipFile) !== true) {
                return $this->result('error', 'Failed to open ZIP', output: $output);
            }

            // Build a lookup set of all entries in the zip (excluding dir markers)
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
            File::delete($lockFile);
        }
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
            'User-Agent' => 'DashboardCore',
            'Accept' => 'application/vnd.github.v3+json',
        ])->withToken($token)
            ->timeout(30)
            ->get($url);

        return $response->successful() ? $response->json() : null;
    }

    private function githubDownload(string $url, string $token): string|false
    {
        $response = Http::withHeaders([
            'User-Agent' => 'DashboardCore',
            'Accept' => 'application/octet-stream',
        ])->withToken($token)
            ->timeout(300)
            ->get($url);

        return $response->successful() ? $response->body() : false;
    }
}
