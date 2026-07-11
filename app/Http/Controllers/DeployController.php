<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class DeployController extends Controller
{
    protected function verifyToken(Request $request): void
    {
        $token = $request->input('token');
        $expected = config('app.deploy_token');

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
                '--email' => env('ADMIN_EMAIL', ''),
                '--password' => env('ADMIN_PASSWORD', ''),
                '--name' => env('ADMIN_NAME', 'Admin'),
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

        return response()->json(['status' => $result ? 'deployed' : 'error', 'output' => $output]);
    }

    private function processUploadedZip(Request $request, array &$output): bool
    {
        $file = $request->file('file');
        $envPath = base_path('.env');
        $backupEnv = File::exists($envPath) ? File::get($envPath) : '';

        $zipFile = $file->storeAs('deploy', 'deploy-uploaded.zip');
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

        // Remove stale files — files on disk that are NOT in the zip.
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
                $staleCandidates++;
                if (! isset($zipFiles[$rel])) {
                    $staleRemoved++;
                    $staleList[] = $rel;
                }
            }
        }
        $staleRatio = $staleCandidates > 0 ? $staleRemoved / $staleCandidates : 0;
        if ($staleRatio > 0.10) {
            $output[] = "Stale cleanup aborted ($staleRemoved/$staleCandidates = ".round($staleRatio * 100).'%)';
        } else {
            foreach ($staleList as $rel) {
                File::delete(base_path($rel));
            }
            $output[] = "Removed $staleRemoved stale files";
        }

        $commands = [
            'package:discover' => [[]],
            'migrate:fresh' => [['--force' => true, '--seed' => true]],
            'app:create-admin' => [[
                '--no-interaction' => true,
                '--email' => env('ADMIN_EMAIL', ''),
                '--password' => env('ADMIN_PASSWORD', ''),
                '--name' => env('ADMIN_NAME', 'Admin'),
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
        $repo = 'nreljed/laravel';

        if (File::exists($lockFile) && (time() - File::lastModified($lockFile)) < 600) {
            return response()->json(['status' => 'locked']);
        }
        File::put($lockFile, getmypid());

        try {
            $releases = $this->githubApi("https://api.github.com/repos/$repo/releases?per_page=1", $githubToken);
            if (! $releases || ! isset($releases[0]['tag_name'])) {
                return response()->json(['status' => 'no-release']);
            }

            $latestTag = $releases[0]['tag_name'];
            $currentTag = File::exists($versionFile) ? trim(File::get($versionFile)) : '';

            if ($latestTag === $currentTag) {
                return response()->json(['status' => 'current', 'tag' => $latestTag]);
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

            // Remove stale files — files on disk that are NOT in the zip.
            // Uses zip entries as the source of truth (no separate manifest needed).
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
                    $staleCandidates++;
                    if (! isset($zipFiles[$rel])) {
                        $staleRemoved++;
                        $staleList[] = $rel;
                    }
                }
            }
            // Safety: if >10% of source files look stale, something is wrong.
            $staleRatio = $staleCandidates > 0 ? $staleRemoved / $staleCandidates : 0;
            if ($staleRatio > 0.10) {
                $output[] = "Stale cleanup aborted (ratio: $staleRatio)";
            } else {
                foreach ($staleList as $rel) {
                    File::delete(base_path($rel));
                }
                $output[] = "Removed $staleRemoved stale files";
            }

            $commands = [
                'package:discover' => [[]],
                'migrate' => [['--force' => true]],
                'app:create-admin' => [[
                    '--no-interaction' => true,
                    '--email' => env('ADMIN_EMAIL', ''),
                    '--password' => env('ADMIN_PASSWORD', ''),
                    '--name' => env('ADMIN_NAME', 'Admin'),
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
                }
            }

            return response()->json(['status' => 'deployed', 'tag' => $latestTag, 'output' => $output]);
        } finally {
            File::delete($lockFile);
        }
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
