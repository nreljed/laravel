<?php

namespace App\Services;

use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class SystemHealthService
{
    public function getAll(): array
    {
        return [
            'php' => $this->getPhpInfo(),
            'laravel' => [
                'version' => app()->version(),
                'environment' => app()->environment(),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
            'opcache' => $this->getOpcacheInfo(),
            'extensions' => $this->getExtensionsInfo(),
            'all_loaded_extensions' => (function () {
                $extensions = get_loaded_extensions();
                sort($extensions);

                return $extensions;
            })(),
            'database' => $this->getDatabaseInfo(),
            'server' => $this->getServerInfo(),
            'environment' => [
                'type' => app()->environment(),
                'detected' => match (true) {
                    str_contains(request()->server('SERVER_SOFTWARE', ''), 'Herd') => 'Herd',
                    str_contains(request()->server('SERVER_SOFTWARE', ''), 'Valet') => 'Valet',
                    str_contains(request()->server('SERVER_SOFTWARE', ''), 'Laragon') => 'Laragon',
                    default => 'Unknown',
                },
            ],
            'recommendations' => $this->getRecommendations(),
            'cache' => [
                'driver' => config('cache.default'),
                'ttl' => config('cache.ttl', 3600),
            ],
            'queue' => [
                'driver' => config('queue.default'),
                'connection' => config('queue.connections.'.config('queue.default').'.driver', config('queue.default')),
            ],
            'mail' => [
                'driver' => config('mail.default'),
            ],
            'session' => [
                'driver' => config('session.driver'),
            ],
            'filesystem' => [
                'default' => config('filesystems.default'),
                'disks' => array_keys(config('filesystems.disks', [])),
            ],
            'modules' => Module::all()->map(fn (Module $m) => [
                'name' => $m->name,
                'slug' => $m->slug,
                'enabled' => $m->enabled,
                'version' => $m->version,
            ])->values()->toArray(),
            'cron' => $this->getScheduledCommands(),
            'logs' => $this->getLogInfo(),
            'users' => [
                'total' => User::count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
                'unverified' => User::whereNull('email_verified_at')->count(),
                'admin' => User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->count(),
            ],
            'build' => $this->getBuildInfo(),
        ];
    }

    public function getPhpInfo(): array
    {
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitMb = $this->convertToMegabytes($memoryLimit);

        return [
            'version' => PHP_VERSION,
            'version_ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'sapi' => PHP_SAPI,
            'memory_limit' => $memoryLimit,
            'memory_limit_mb' => $memoryLimitMb,
            'memory_limit_ok' => $memoryLimitMb >= 128,
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'max_input_vars' => ini_get('max_input_vars'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'ini_path' => php_ini_loaded_file() ?: 'N/A',
        ];
    }

    public function getOpcacheInfo(): array
    {
        $loaded = extension_loaded('Zend OPcache');
        $enabled = $loaded && ini_get('opcache.enable');

        $config = [];
        if ($loaded) {
            $keys = [
                'opcache.enable',
                'opcache.memory_consumption',
                'opcache.interned_strings_buffer',
                'opcache.max_accelerated_files',
                'opcache.revalidate_freq',
                'opcache.validate_timestamps',
                'opcache.max_wasted_percentage',
                'opcache.use_cwd',
                'opcache.file_cache',
                'opcache.jit',
                'opcache.jit_buffer_size',
            ];
            foreach ($keys as $key) {
                $config[str_replace('opcache.', '', $key)] = ini_get($key);
            }
        }

        $status = null;
        if ($enabled && function_exists('opcache_get_status')) {
            $raw = opcache_get_status(false);
            if ($raw) {
                $memory = $raw['memory_usage'] ?? [];
                $stats = $raw['opcache_statistics'] ?? [];
                $usedMemory = $memory['used_memory'] ?? 0;
                $freeMemory = $memory['free_memory'] ?? 0;
                $wastedMemory = $memory['wasted_memory'] ?? 0;
                $totalMemory = $usedMemory + $freeMemory + $wastedMemory;
                $hits = $stats['hits'] ?? 0;
                $misses = $stats['misses'] ?? 0;
                $totalRequests = $hits + $misses;
                $hitRate = $totalRequests > 0 ? round(($hits / $totalRequests) * 100, 2) : 0;

                $status = [
                    'used_memory' => $this->formatBytes((int) $usedMemory),
                    'free_memory' => $this->formatBytes((int) $freeMemory),
                    'wasted_memory' => $this->formatBytes((int) $wastedMemory),
                    'cached_files' => $stats['num_cached_scripts'] ?? 0,
                    'max_cached_files' => $stats['max_cached_keys'] ?? 0,
                    'hits' => $hits,
                    'misses' => $misses,
                    'hit_rate' => $hitRate,
                    'hit_rate_ok' => $hitRate >= 95,
                    'memory_usage_percent' => $totalMemory > 0
                        ? round(($usedMemory / $totalMemory) * 100, 2)
                        : 0,
                ];
            }
        }

        return [
            'extension_loaded' => $loaded,
            'enabled' => $enabled,
            'config' => $config,
            'status' => $status,
        ];
    }

    public function getExtensionsInfo(): array
    {
        $required = [
            'pdo',
            'pdo_mysql',
            'mbstring',
            'gd',
            'curl',
            'xml',
            'zip',
            'bcmath',
            'intl',
            'openssl',
            'fileinfo',
            'dom',
            'json',
            'tokenizer',
            'ctype',
            'redis',
            'imagick',
        ];

        return array_map(fn (string $ext) => [
            'name' => $ext,
            'loaded' => extension_loaded($ext),
            'required' => true,
        ], $required);
    }

    public function getDatabaseInfo(): array
    {
        $connection = config('database.default');
        $driver = config('database.connections.'.$connection.'.driver', 'N/A');
        $name = config('database.connections.'.$connection.'.database', 'N/A');

        $version = 'N/A';
        try {
            $result = DB::select('SELECT VERSION()');
            $version = $result[0]->{'VERSION()'} ?? $result[0]->version ?? 'N/A';
        } catch (\Exception) {
        }

        $size = 'N/A';
        try {
            $size = $this->getDbSize();
        } catch (\Exception) {
        }

        return [
            'connection' => $connection,
            'driver' => $driver,
            'name' => $name,
            'version' => $version,
            'size' => $size,
            'charset' => config('database.connections.'.$connection.'.charset', 'utf8mb4'),
            'collation' => config('database.connections.'.$connection.'.collation', 'utf8mb4_unicode_ci'),
        ];
    }

    public function getServerInfo(): array
    {
        return [
            'software' => request()->server('SERVER_SOFTWARE', 'N/A'),
            'is_https' => (! empty(request()->server('HTTPS')) && request()->server('HTTPS') !== 'off')
                || (int) (request()->server('SERVER_PORT', 80)) === 443,
            'document_root' => request()->server('DOCUMENT_ROOT', 'N/A'),
            'server_ip' => request()->server('SERVER_ADDR')
                ?? (gethostbyname(gethostname()) ?: 'N/A'),
            'server_protocol' => request()->server('SERVER_PROTOCOL', 'N/A'),
            'http_host' => request()->server('HTTP_HOST', 'N/A'),
        ];
    }

    public function getRecommendations(): array
    {
        $recommendations = [];
        $phpInfo = $this->getPhpInfo();
        $extensions = $this->getExtensionsInfo();
        $server = $this->getServerInfo();
        $isProduction = app()->environment('production');

        if (! $phpInfo['version_ok']) {
            $recommendations[] = [
                'severity' => 'critical',
                'title' => 'PHP version outdated',
                'message' => 'PHP '.$phpInfo['version'].' is below the minimum 8.2.0.',
                'fix' => 'Upgrade PHP to 8.2 or higher.',
            ];
        }

        if (! $phpInfo['memory_limit_ok']) {
            $recommendations[] = [
                'severity' => 'high',
                'title' => 'Low PHP memory limit',
                'message' => 'Memory limit is '.$phpInfo['memory_limit']
                    .' ('.$phpInfo['memory_limit_mb'].'M). Minimum 128M recommended.',
                'fix' => 'Set memory_limit to at least 128M in php.ini.',
            ];
        }

        foreach ($extensions as $ext) {
            if (! $ext['loaded']) {
                $recommendations[] = [
                    'severity' => 'critical',
                    'title' => 'Missing PHP extension: '.$ext['name'],
                    'message' => 'Required extension '.$ext['name'].' is not loaded.',
                    'fix' => 'Install or enable the '.$ext['name'].' PHP extension.',
                ];
            }
        }

        if ($isProduction && config('app.debug')) {
            $recommendations[] = [
                'severity' => 'critical',
                'title' => 'Debug mode enabled in production',
                'message' => 'APP_DEBUG is true in a production environment.',
                'fix' => 'Set APP_DEBUG=false in your .env file.',
            ];
        }

        if ($isProduction && ! $server['is_https']) {
            $recommendations[] = [
                'severity' => 'high',
                'title' => 'HTTPS not enabled',
                'message' => 'Site is not using HTTPS in production.',
                'fix' => 'Enable SSL/TLS and force HTTPS redirect.',
            ];
        }

        $cacheDriver = config('cache.default');
        if ($cacheDriver === 'file') {
            $recommendations[] = [
                'severity' => 'info',
                'title' => 'File-based cache in use',
                'message' => 'File cache driver may be slow under high load.',
                'fix' => 'Consider using redis or memcached for better performance.',
            ];
        }

        $queueDriver = config('queue.default');
        if ($queueDriver === 'sync') {
            $recommendations[] = [
                'severity' => 'info',
                'title' => 'Sync queue driver in use',
                'message' => 'Queue jobs are processed synchronously, blocking responses.',
                'fix' => 'Configure a queue driver like database or redis.',
            ];
        }

        if (extension_loaded('Zend OPcache') && ! ini_get('opcache.enable')) {
            $recommendations[] = [
                'severity' => 'info',
                'title' => 'OPcache is installed but disabled',
                'message' => 'OPcache can significantly improve PHP performance.',
                'fix' => 'Enable OPcache in php.ini by setting opcache.enable=1.',
            ];
        }

        return $recommendations;
    }

    public function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1073741824 => round($bytes / 1073741824, 2).' GB',
            $bytes >= 1048576 => round($bytes / 1048576, 2).' MB',
            $bytes >= 1024 => round($bytes / 1024, 2).' KB',
            default => $bytes.' B',
        };
    }

    public function convertToMegabytes(string $value): int
    {
        $value = trim($value);

        if ($value === '-1' || $value === '') {
            return -1;
        }

        $unit = strtoupper($value[-1]);
        $number = (int) substr($value, 0, -1);

        return match ($unit) {
            'G' => $number * 1024,
            'M' => $number,
            'K' => (int) round($number / 1024),
            default => (int) $value === 0 ? 0 : (int) round((int) $value / 1048576),
        };
    }

    protected function getDbSize(): string
    {
        $connection = config('database.default');
        $driver = config('database.connections.'.$connection.'.driver');
        $name = config('database.connections.'.$connection.'.database', 'N/A');

        return match ($driver) {
            'sqlite' => $this->getSqliteSize($name),
            'mysql' => $this->getMysqlSize($name),
            default => 'N/A',
        };
    }

    protected function getSqliteSize(string $path): string
    {
        if (file_exists($path)) {
            $bytes = filesize($path);

            return $this->formatBytes($bytes);
        }

        $result = DB::select(
            'SELECT ROUND(CAST(page_count * page_size AS REAL), 0) AS size_bytes
             FROM pragma_page_count, pragma_page_size'
        );

        $bytes = (int) ($result[0]->size_bytes ?? 0);

        return $bytes > 0 ? $this->formatBytes($bytes) : 'N/A';
    }

    protected function getMysqlSize(string $database): string
    {
        $result = DB::select(
            'SELECT ROUND(SUM(data_length + index_length), 0) AS size_bytes
             FROM information_schema.tables
             WHERE table_schema = ?',
            [$database]
        );

        $bytes = (int) ($result[0]->size_bytes ?? 0);

        return $bytes > 0 ? $this->formatBytes($bytes) : 'N/A';
    }

    protected function getScheduledCommands(): array
    {
        $commands = Artisan::all();
        $appNamespace = 'App\\';
        $scheduled = [];

        foreach ($commands as $name => $command) {
            $class = get_class($command);
            if (str_starts_with($class, $appNamespace)) {
                $scheduled[] = [
                    'name' => $name,
                    'description' => $command->getDescription() ?: 'N/A',
                    'class' => $class,
                ];
            }
        }

        usort($scheduled, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $scheduled;
    }

    protected function getLogInfo(): array
    {
        $channel = Config::get('logging.default');
        $channels = Config::get('logging.channels', []);
        $channelConfig = $channels[$channel] ?? [];
        $driver = $channelConfig['driver'] ?? 'stack';
        $level = $channelConfig['level'] ?? 'debug';
        $path = null;

        if (in_array($driver, ['single', 'daily'], true)) {
            $path = $channelConfig['path']
                ?? storage_path('logs/laravel.log');
        }

        return [
            'channel' => $channel,
            'level' => $level,
            'path' => $path,
        ];
    }

    protected function getBuildInfo(): array
    {
        $path = public_path('build/manifest.json');

        if (! file_exists($path)) {
            return ['exists' => false];
        }

        $modified = filemtime($path);

        return [
            'exists' => true,
            'modified_at' => date('Y-m-d H:i:s', $modified),
            'modified_timestamp' => $modified,
            'age' => now()->diffInDays(now()->setTimestamp($modified)).' days',
        ];
    }
}
