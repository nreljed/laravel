<?php

namespace App\Services;

use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemHealthService
{
    public function getDiagnostics(): array
    {
        return [
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'environment' => app()->environment(),
            'debug' => config('app.debug'),
            'url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),

            'dbConnection' => config('database.default'),
            'dbDriver' => config('database.connections.' . config('database.default') . '.driver'),
            'dbName' => config('database.connections.' . config('database.default') . '.database', 'N/A'),
            'dbSize' => $this->getDbSize(),

            'cacheDriver' => config('cache.default'),
            'cacheSize' => $this->getCacheSize(),
            'sessionDriver' => config('session.driver'),
            'queueDriver' => config('queue.default'),
            'mailDriver' => config('mail.default'),

            'phpExtensions' => $this->getPhpExtensions(),
            'phpMemoryLimit' => ini_get('memory_limit'),
            'phpMaxUploadSize' => ini_get('upload_max_filesize'),
            'phpMaxPostSize' => ini_get('post_max_size'),
            'phpMaxExecTime' => ini_get('max_execution_time'),

            'serverSoftware' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'serverOs' => PHP_OS,

            'totalUsers' => User::count(),
            'enabledModules' => Module::where('enabled', true)->count(),
        ];
    }

    protected function getDbSize(): string
    {
        try {
            $dbDriver = config('database.connections.' . config('database.default') . '.driver');
            $dbName = config('database.connections.' . config('database.default') . '.database', 'N/A');

            if ($dbDriver === 'sqlite') {
                $size = DB::select('SELECT ROUND(SUM(page_count * page_size) / 1024.0, 2) AS size_kb FROM pragma_page_count(), pragma_page_size()')[0]->size_kb ?? 0;
            } else {
                $size = DB::select('SELECT ROUND(SUM(data_length + index_length) / 1024, 2) AS size_kb FROM information_schema.tables WHERE table_schema = ?', [$dbName])[0]->size_kb ?? 0;
            }

            return round($size / 1024, 2) . ' MB';
        } catch (\Exception) {
            return 'N/A';
        }
    }

    protected function getCacheSize(): string
    {
        try {
            if (Cache::getDefaultDriver() === 'file') {
                $cachePath = storage_path('framework/cache/data');
                if (is_dir($cachePath)) {
                    $size = 0;
                    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cachePath));
                    foreach ($iterator as $file) {
                        if ($file->isFile()) $size += $file->getSize();
                    }
                    return $size > 0 ? round($size / 1024 / 1024, 2) . ' MB' : 'N/A';
                }
            }
            return 'N/A';
        } catch (\Exception) {
            return 'N/A';
        }
    }

    protected function getPhpExtensions(): array
    {
        $extStatus = [];
        $requiredExts = ['mbstring', 'pdo', 'fileinfo', 'gd', 'json', 'xml', 'curl', 'openssl', 'tokenizer', 'bcmath', 'intl'];
        foreach ($requiredExts as $ext) {
            $extStatus[$ext] = extension_loaded($ext);
        }
        return $extStatus;
    }
}
