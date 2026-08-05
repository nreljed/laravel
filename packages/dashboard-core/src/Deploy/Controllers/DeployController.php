<?php

namespace Nreljed\DashboardCore\Deploy\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Nreljed\DashboardCore\Exceptions\DatabaseMaintenanceException;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Services\CoreVersionService;
use Nreljed\DashboardCore\Services\DatabaseMaintenanceService;

class DeployController extends Controller
{
    protected function verifyToken(Request $request): void
    {
        $token = $request->input('token');
        $expected = config('deploy.token');

        if (! $expected || ! hash_equals((string) $expected, (string) $token)) {
            abort(403, 'Invalid or missing deploy token.');
        }
    }

    public function updateEnv(Request $request)
    {
        $this->verifyToken($request);

        $content = $request->input('content');

        if (! $content || trim($content) === '') {
            return response()->json(['error' => 'No content provided'], 400);
        }

        if (! is_string($content)) {
            return response()->json(['error' => 'Content must be a string'], 400);
        }

        $lineCount = substr_count($content, "\n") + 1;
        if ($lineCount > 200) {
            return response()->json(['error' => 'Content exceeds maximum allowed lines (200)'], 400);
        }

        $forbiddenKeys = ['APP_KEY', 'DEPLOY_TOKEN'];
        foreach ($forbiddenKeys as $key) {
            if (preg_match('/^'.preg_quote($key, '/').'\s*=/m', $content)) {
                return response()->json(['error' => "Setting {$key} cannot be modified via this endpoint."], 403);
            }
        }

        $envPath = base_path('.env');

        if (File::exists($envPath)) {
            File::copy($envPath, $envPath.'.backup.'.now()->timestamp);
        }

        File::put($envPath, $content);

        Artisan::call('config:clear');

        return response()->json(['success' => true, 'message' => '.env written']);
    }

    public function cacheClear(Request $request)
    {
        $this->verifyToken($request);
        set_time_limit(300);

        $output = [];

        // Wipe all cached files first to break the chicken-and-egg cycle
        $this->wipeCache();

        $commands = [
            'migrate:fresh' => [['--force' => true, '--seed' => true]],
            'cache:clear' => [[]],
            'package:discover' => [[]],
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
                $output[$cmd] = strtok(trim(Artisan::output()), "\n") ?: 'ok';
            } catch (\Throwable $e) {
                $output[$cmd] = 'ERROR: '.$e->getMessage();
            }
        }

        // Switch to database sessions now that tables exist
        $envPath = base_path('.env');
        $env = File::get($envPath);
        $env = preg_replace('/^SESSION_DRIVER=file$/m', 'SESSION_DRIVER=database', $env);
        $env = preg_replace('/^QUEUE_CONNECTION=sync$/m', 'QUEUE_CONNECTION=database', $env);
        File::put($envPath, $env);

        // Clean up deploy-only files
        $this->cleanupDeployFiles();

        return response()->json(['success' => true, 'output' => $output]);
    }

    public function migrate(Request $request)
    {
        $this->verifyToken($request);
        set_time_limit(120);

        $output = [];

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output[] = 'migrate: '.strtok(trim(Artisan::output()), "\n");

            return response()->json(['status' => 'ok', 'output' => $output]);
        } catch (\Throwable $e) {
            $output[] = 'migrate: ERROR '.$e->getMessage();

            return response()->json(['status' => 'error', 'output' => $output], 500);
        }
    }

    public function resetDatabase(Request $request, DatabaseMaintenanceService $service)
    {
        $this->verifyToken($request);
        set_time_limit(300);

        if ($request->input('confirm') !== 'RESET') {
            return response()->json(['status' => 'error', 'error' => 'Confirmation required. Send confirm=RESET.'], 422);
        }

        try {
            $result = $service->resetDatabase(
                withSeed: $request->boolean('with_seed', true),
                purgeMedia: $request->boolean('purge_media'),
                userId: 0,
            );

            return response()->json(['status' => 'ok'] + $result);
        } catch (DatabaseMaintenanceException $e) {
            return response()->json(['status' => 'error', 'error' => $e->getMessage()], 500);
        }
    }

    public function createAdmin(Request $request)
    {
        $this->verifyToken($request);
        set_time_limit(60);

        $password = $request->input('password');
        if (! $password || strlen($password) < 8) {
            return response()->json(['error' => 'Password must be at least 8 characters'], 400);
        }

        $email = $request->input('email', config('admin.email', 'nstudio.webdesign@gmail.com'));
        $name = $request->input('name', config('admin.name', 'Studio Admin'));

        try {
            Artisan::call('app:create-admin', [
                '--no-interaction' => true,
                '--email' => $email,
                '--password' => $password,
                '--name' => $name,
            ]);
            $line = strtok(trim(Artisan::output()), "\n");

            return response()->json(['status' => 'ok', 'message' => $line]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'error' => $e->getMessage()], 500);
        }
    }

    public function upload(Request $request)
    {
        $this->verifyToken($request);
        set_time_limit(300);

        if (! $request->hasFile('file')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');
        if ($file->getClientOriginalExtension() !== 'zip') {
            return response()->json(['error' => 'File must be a zip'], 400);
        }

        $output = [];
        $result = $this->processUploadedZip($request, $output);

        if (! $result) {
            return response()->json(['status' => 'error', 'output' => $output], 500);
        }

        return response()->json(['status' => 'deployed', 'output' => $output]);
    }

    private function processUploadedZip(Request $request, array &$output): bool
    {
        $file = $request->file('file');
        $envPath = base_path('.env');
        $backupEnv = File::exists($envPath) ? File::get($envPath) : '';

        $storageDir = storage_path('app/deploy');
        File::ensureDirectoryExists($storageDir);
        $zipFile = $file->storeAs('deploy', 'deploy-uploaded.zip');
        if ($zipFile === false) {
            $output[] = 'ERROR: Failed to store uploaded file';

            return false;
        }
        $zipPath = storage_path("app/$zipFile");
        $output[] = 'Received '.round(filesize($zipPath) / 1048576, 1).' MB';

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            $output[] = 'ERROR: Failed to open ZIP';

            return false;
        }

        // Build lookup set of all zip entries for stale cleanup
        $zipFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat && $stat['name'] !== '' && ! str_ends_with($stat['name'], '/')) {
                $zipFiles[str_replace('\\', '/', $stat['name'])] = true;
            }
        }

        $zip->extractTo(base_path());
        $zip->close();
        File::delete($zipPath);
        $output[] = 'Extracted ('.count($zipFiles).' files)';

        if ($backupEnv) {
            File::put($envPath, $backupEnv);
            $output[] = 'Restored .env';
        }

        $stale = $this->removeStaleFiles($zipFiles);
        if ($stale['aborted']) {
            $output[] = "Stale cleanup aborted ($stale[candidates] = ".round($stale['ratio'] * 100).'%)';
        } else {
            $output[] = "Removed $stale[removed] stale files";
        }

        $this->cleanStaleMigrations($zipFiles, $output);

        $commands = [
            'package:discover' => [[]],
            'migrate:fresh' => [['--force' => true, '--seed' => true]],
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
            } catch (\Throwable $e) {
                $output[] = "$cmd: ERROR ".$e->getMessage();
                if (in_array($cmd, ['migrate:fresh', 'migrate', 'config:cache'])) {
                    $output[] = "Deploy aborted: $cmd failed";

                    return false;
                }
            }
        }

        return true;
    }

    private function cleanupDeployFiles(): void
    {
        $files = [
            public_path('.user.ini'),
            public_path('opcache-reset.php'),
        ];

        foreach ($files as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }

    private function removeStaleFiles(array $zipFiles): array
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

    private function cleanStaleMigrations(array $zipFiles, array &$output): void
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

    private function wipeCache(): void
    {
        $paths = [
            base_path('bootstrap/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/views'),
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                File::delete(File::glob("{$path}/*.php"));
                File::delete(File::glob("{$path}/*.json"));
            }
        }
    }

    public function check(Request $request)
    {
        $this->verifyToken($request);
        set_time_limit(300);

        $output = [];
        $envPath = base_path('.env');

        // If a file was uploaded directly (CI fallback), bypass GitHub API entirely
        if ($request->hasFile('file')) {
            return $this->processUploadedZip($request, $output);
        }

        $githubToken = '';
        if (File::exists($envPath)) {
            foreach (explode("\n", File::get($envPath)) as $line) {
                if (str_starts_with(trim($line), 'GITHUB_TOKEN=')) {
                    $githubToken = trim(substr(trim($line), strlen('GITHUB_TOKEN=')));
                    $githubToken = trim($githubToken, '"\'');
                }
            }
        }

        $versionFile = base_path('.deployed-version');
        $lockFile = base_path('.deploy-lock');
        $repo = config('deploy.github_repo', 'nreljed/laravel');
        $lockTtl = (int) config('deploy.lock_ttl', 600);

        if (File::exists($lockFile) && (time() - File::lastModified($lockFile)) < $lockTtl) {
            return response()->json(['status' => 'locked']);
        }
        File::put($lockFile, (string) getmypid());

        try {
            $releases = $this->githubApi("https://api.github.com/repos/$repo/releases?per_page=1", $githubToken);
            if (! $releases || ! isset($releases[0]['tag_name'])) {
                return response()->json(['status' => 'no-release', 'core' => $this->getCoreInfo()]);
            }

            $latestTag = $releases[0]['tag_name'];
            $currentTag = File::exists($versionFile) ? trim(File::get($versionFile)) : '';

            if ($latestTag === $currentTag) {
                return response()->json(['status' => 'current', 'tag' => $latestTag, 'core' => $this->getCoreInfo()]);
            }

            $assetUrl = '';
            foreach ($releases[0]['assets'] ?? [] as $asset) {
                if ($asset['name'] === 'deploy.zip') {
                    $assetUrl = $asset['url'];
                    break;
                }
            }

            if (! $assetUrl) {
                return response()->json(['error' => 'No deploy.zip in release']);
            }

            $output[] = "New release: $latestTag";

            $zipData = $this->githubDownload($assetUrl, $githubToken);
            if (! $zipData || strlen($zipData) < 100) {
                return response()->json(['error' => 'Download failed']);
            }

            $zipFile = storage_path('app/deploy-'.uniqid().'.zip');
            File::put($zipFile, $zipData);
            $output[] = 'Downloaded '.round(strlen($zipData) / 1048576, 1).' MB';

            $backupEnv = File::exists($envPath) ? File::get($envPath) : '';

            $zip = new \ZipArchive;
            if ($zip->open($zipFile) !== true) {
                return response()->json(['error' => 'Failed to open ZIP']);
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
                        return response()->json(['status' => 'error', 'error' => "$cmd failed", 'output' => $output], 500);
                    }
                }
            }

            return response()->json(['status' => 'deployed', 'tag' => $latestTag, 'output' => $output, 'core' => $this->getCoreInfo()]);
        } finally {
            File::delete($lockFile);
        }
    }

    private function getCoreInfo(): array
    {
        return app(CoreVersionService::class)->info();
    }

    private function githubApi(string $url, string $token): ?array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'CostBreaker360',
            'Accept' => 'application/vnd.github.v3+json',
        ])->withToken($token)
            ->timeout(30)
            ->get($url);

        return $response->successful() ? $response->json() : null;
    }

    private function githubDownload(string $url, string $token): string|false
    {
        $response = Http::withHeaders([
            'User-Agent' => 'CostBreaker360',
            'Accept' => 'application/octet-stream',
        ])->withToken($token)
            ->timeout(300)
            ->get($url);

        return $response->successful() ? $response->body() : false;
    }
}
