<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class DeployController extends Controller
{
    protected function verifyToken(Request $request): void
    {
        $token = $request->input('token', $request->query('token'));
        $expected = config('app.deploy_token');

        if (!$expected || $token !== $expected) {
            abort(403, 'Invalid or missing deploy token.');
        }
    }

    public function updateEnv(Request $request)
    {
        $this->verifyToken($request);

        $content = $request->input('content');

        if (!$content || trim($content) === '') {
            return response()->json(['error' => 'No content provided'], 400);
        }

        $envPath = base_path('.env');

        if (file_exists($envPath)) {
            copy($envPath, $envPath . '.backup.' . now()->timestamp);
        }

        file_put_contents($envPath, $content);

        Artisan::call('config:clear');

        return response()->json(['success' => true, 'message' => '.env written']);
    }

    public function cacheClear(Request $request)
    {
        $this->verifyToken($request);

        // Wipe all cached files first to break the chicken-and-egg cycle
        $this->wipeCache();

        // Now that cache is gone, run fresh migrations (wipe old Laravel 11 tables)
        Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);
        $output['migrate:fresh'] = Artisan::output();

        // Switch back to database sessions now that tables exist
        $envPath = base_path('.env');
        $env = file_get_contents($envPath);
        $env = preg_replace('/^SESSION_DRIVER=file$/m', 'SESSION_DRIVER=database', $env);
        $env = preg_replace('/^QUEUE_CONNECTION=sync$/m', 'QUEUE_CONNECTION=database', $env);
        file_put_contents($envPath, $env);

        Artisan::call('cache:clear');
        $output['cache:clear'] = Artisan::output();

        // Re-cache for production
        Artisan::call('config:cache');
        $output['config:cache'] = Artisan::output();

        Artisan::call('route:cache');
        $output['route:cache'] = Artisan::output();

        Artisan::call('view:cache');
        $output['view:cache'] = Artisan::output();

        // Clean up deploy-only auto-prepend files now that the app works
        $this->cleanupDeployFiles();

        return response()->json(['success' => true, 'output' => $output]);
    }

    private function cleanupDeployFiles(): void
    {
        $files = [
            public_path('.user.ini'),
            public_path('opcache-reset.php'),
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
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
        $githubToken = '';
        $envPath = base_path('.env');

        if (file_exists($envPath)) {
            foreach (file($envPath) as $line) {
                if (str_starts_with(trim($line), 'GITHUB_TOKEN=')) {
                    $githubToken = trim(substr(trim($line), strlen('GITHUB_TOKEN=')));
                    $githubToken = trim($githubToken, '"\'');
                }
            }
        }

        $versionFile = base_path('.deployed-version');
        $lockFile = base_path('.deploy-lock');
        $repo = 'nreljed/laravel';

        if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 600) {
            return response()->json(['status' => 'locked']);
        }
        file_put_contents($lockFile, getmypid());

        try {
            $releases = $this->githubApi("https://api.github.com/repos/$repo/releases?per_page=1", $githubToken);
            if (!$releases || !isset($releases[0]['tag_name'])) {
                return response()->json(['status' => 'no-release']);
            }

            $latestTag = $releases[0]['tag_name'];
            $currentTag = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '';

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

            if (!$assetUrl) {
                return response()->json(['error' => 'No deploy.zip in release']);
            }

            $output[] = "New release: $latestTag";

            $zipData = $this->githubDownload($assetUrl, $githubToken);
            if (!$zipData || strlen($zipData) < 100) {
                return response()->json(['error' => 'Download failed']);
            }

            $zipFile = storage_path('app/deploy-' . uniqid() . '.zip');
            file_put_contents($zipFile, $zipData);
            $output[] = 'Downloaded ' . round(strlen($zipData) / 1048576, 1) . ' MB';

            $backupEnv = file_exists($envPath) ? file_get_contents($envPath) : '';

            $zip = new \ZipArchive;
            if ($zip->open($zipFile) !== true) {
                return response()->json(['error' => 'Failed to open ZIP']);
            }
            $zip->extractTo(base_path());
            $zip->close();
            @unlink($zipFile);

            if ($backupEnv) {
                file_put_contents($envPath, $backupEnv);
                $output[] = 'Restored .env';
            }

            file_put_contents($versionFile, $latestTag);
            $output[] = "New version: $latestTag";

            $commands = [
                'package:discover' => [[]],
                'migrate' => [['--force' => true]],
                'config:cache' => [[]],
                'route:cache' => [[]],
                'view:cache' => [[]],
                'storage:link' => [['--force' => true]],
            ];

            foreach ($commands as $cmd => [$params]) {
                try {
                    Artisan::call($cmd, $params);
                    $output[] = "$cmd: " . strtok(trim(Artisan::output()), "\n");
                } catch (\Exception $e) {
                    $output[] = "$cmd: ERROR " . $e->getMessage();
                }
            }

            return response()->json(['status' => 'deployed', 'tag' => $latestTag, 'output' => $output]);
        } finally {
            @unlink($lockFile);
        }
    }

    private function githubApi($url, $token): array|null
    {
        $headers = ['User-Agent: CostBreaker360', 'Accept: application/vnd.github.v3+json'];
        if ($token) $headers[] = "Authorization: Bearer $token";
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => 30]]);
        $response = @file_get_contents($url, false, $ctx);
        return $response ? json_decode($response, true) : null;
    }

    private function githubDownload($url, $token): string|false
    {
        $headers = ['User-Agent: CostBreaker360', 'Accept: application/octet-stream'];
        if ($token) $headers[] = "Authorization: Bearer $token";
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => 300]]);
        return @file_get_contents($url, false, $ctx);
    }
}
