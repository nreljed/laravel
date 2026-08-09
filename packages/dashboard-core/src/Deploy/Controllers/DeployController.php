<?php

namespace Nreljed\DashboardCore\Deploy\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Nreljed\DashboardCore\Deploy\Services\DeployService;
use Nreljed\DashboardCore\Exceptions\DatabaseMaintenanceException;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Services\DatabaseMaintenanceService;

class DeployController extends Controller
{
    public function __construct(private readonly DeployService $deployService) {}

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

    public function updateEnvValues(Request $request)
    {
        $this->verifyToken($request);

        $values = $request->input('values');

        if (! is_array($values) || $values === []) {
            return response()->json(['error' => 'No values provided'], 400);
        }

        $forbiddenKeys = ['APP_KEY', 'DEPLOY_TOKEN'];
        $safe = [];

        foreach ($values as $key => $value) {
            $key = trim((string) $key);
            if ($key === '' || preg_match('/\s/', $key)) {
                return response()->json(['error' => "Invalid environment key: {$key}"], 400);
            }
            if (in_array($key, $forbiddenKeys, true)) {
                return response()->json(['error' => "Setting {$key} cannot be modified via this endpoint."], 403);
            }
            $safe[$key] = $value === null ? '' : (string) $value;
        }

        $envPath = base_path('.env');

        if (File::exists($envPath)) {
            File::copy($envPath, $envPath.'.backup.'.now()->timestamp);
            $content = File::get($envPath);
        } else {
            $content = '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $written = [];

        foreach ($lines as &$line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $line, $m)) {
                $key = $m[1];
                if (array_key_exists($key, $safe)) {
                    $line = "{$key}={$safe[$key]}";
                    $written[$key] = true;
                }
            }
        }
        unset($line);

        foreach ($safe as $key => $value) {
            if (! isset($written[$key])) {
                $lines[] = "{$key}={$value}";
            }
        }

        File::put($envPath, implode(PHP_EOL, $lines).PHP_EOL);

        Artisan::call('config:clear');

        return response()->json(['success' => true, 'updated' => array_keys($safe)]);
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
                purgeMedia: $request->boolean('purge_media'),
                userId: 0,
                demo: $request->boolean('with_demo'),
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
        $zipPath = $storageDir.'/deploy-uploaded.zip';

        try {
            $file->move($zipPath);
        } catch (\Throwable $e) {
            $output[] = 'ERROR: Failed to store uploaded file: '.$e->getMessage();

            return false;
        }

        if (! file_exists($zipPath)) {
            $output[] = "ERROR: Uploaded file missing at $zipPath";

            return false;
        }

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

        $stale = $this->deployService->removeStaleFiles($zipFiles);
        if ($stale['aborted']) {
            $output[] = "Stale cleanup aborted ($stale[candidates] = ".round($stale['ratio'] * 100).'%)';
        } else {
            $output[] = "Removed $stale[removed] stale files";
        }

        $this->deployService->cleanStaleMigrations($zipFiles, $output);

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
        return $this->deployService->removeStaleFiles($zipFiles);
    }

    private function cleanStaleMigrations(array $zipFiles, array &$output): void
    {
        $this->deployService->cleanStaleMigrations($zipFiles, $output);
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

        // If a file was uploaded directly (CI fallback), bypass GitHub API entirely
        if ($request->hasFile('file')) {
            $output = [];

            return $this->processUploadedZip($request, $output);
        }

        $result = $this->deployService->check();

        return match ($result['status']) {
            'deployed' => response()->json([
                'status' => 'deployed',
                'tag' => $result['tag'],
                'output' => $result['output'],
                'core' => $result['core'],
            ]),
            'current' => response()->json([
                'status' => 'current',
                'tag' => $result['tag'],
                'core' => $result['core'],
            ]),
            'locked' => response()->json(['status' => 'locked']),
            'no-release' => response()->json([
                'status' => 'no-release',
                'core' => $result['core'],
            ]),
            default => $this->errorResponse($result),
        };
    }

    /**
     * @param  array{status: string, message: string, tag: string|null, output: array<int, string>, core: array<string, mixed>, status_code: int}  $result
     */
    private function errorResponse(array $result)
    {
        if ($result['status_code'] === 500) {
            return response()->json([
                'status' => 'error',
                'error' => $result['message'],
                'output' => $result['output'],
            ], 500);
        }

        return response()->json(['error' => $result['message']], $result['status_code']);
    }
}
