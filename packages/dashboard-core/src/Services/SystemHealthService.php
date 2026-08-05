<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Nreljed\DashboardCore\Models\User;
use Symfony\Component\Console\Command\Command;

class SystemHealthService
{
    public function getAll(): array
    {
        $php = $this->getPhpInfo();
        $extensions = $this->getExtensionsInfo();
        $server = $this->getServerInfo();
        $storage = $this->getStorageInfo();
        $deployed = $this->getDeployedVersion();
        $latest = $this->getLatestVersion();
        $defaultMailer = config('mail.default');

        return [
            'php' => $php,
            'laravel' => [
                'version' => app()->version(),
                'environment' => app()->environment(),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
                'name' => config('app.name'),
            ],
            'opcache' => $this->getOpcacheInfo(),
            'extensions' => $extensions,
            'all_loaded_extensions' => (function () {
                $extensions = get_loaded_extensions();
                sort($extensions);

                return $extensions;
            })(),
            'database' => $this->getDatabaseInfo(),
            'server' => $server,
            'os' => $this->getOsInfo(),
            'environment' => $this->detectEnvironment(),
            'recommendations' => $this->getRecommendations($php, $extensions, $server, $storage, $deployed, $latest),
            'cache' => [
                'driver' => config('cache.default'),
                'ttl' => config('cache.ttl', 3600),
                'stores' => array_keys(config('cache.stores', [])),
            ],
            'queue' => [
                'driver' => config('queue.default'),
                'connection' => config('queue.connections.'.config('queue.default').'.driver', config('queue.default')),
            ],
            'mail' => [
                'driver' => $defaultMailer,
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'mailer' => array_filter([
                    'transport' => config("mail.mailers.{$defaultMailer}.transport"),
                    'host' => config("mail.mailers.{$defaultMailer}.host"),
                    'port' => config("mail.mailers.{$defaultMailer}.port"),
                    'username' => config("mail.mailers.{$defaultMailer}.username"),
                    'encryption' => config("mail.mailers.{$defaultMailer}.encryption"),
                ], static fn ($value) => $value !== null),
            ],
            'session' => [
                'driver' => config('session.driver'),
                'lifetime' => config('session.lifetime'),
                'secure' => config('session.secure'),
                'same_site' => config('session.same_site'),
            ],
            'filesystem' => [
                'default' => config('filesystems.default'),
                'disks' => array_keys(config('filesystems.disks', [])),
            ],
            'modules' => app()->bound(FeatureRegistry::class)
                ? collect(app(FeatureRegistry::class)->getAll())->map(fn ($m) => [
                    'name' => $m['name'],
                    'slug' => $m['slug'],
                    'enabled' => $m['enabled'] ?? true,
                    'version' => $m['version'] ?? null,
                ])->values()->toArray()
                : [],
            'schedule' => $this->getScheduleInfo(),
            'logs' => $this->getLogInfo(),
            'users' => [
                'total' => User::count(),
                'verified' => User::whereNotNull('email_verified_at')->count(),
                'unverified' => User::whereNull('email_verified_at')->count(),
                'admin' => User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->count(),
            ],
            'build' => $this->getBuildInfo(),
            'storage' => $storage,
            'deployed_version' => $deployed,
            'latest_version' => $latest,
            'core_package' => $this->getCorePackage(),
            'laravel_cache' => $this->getLaravelCacheStatus(),
            'debug_access_url' => $this->getDebugAccessUrl(),
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
            'memory_limit_ok' => $memoryLimitMb === -1 || $memoryLimitMb >= 128,
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'max_input_vars' => ini_get('max_input_vars'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => error_reporting(),
            'ini_path' => php_ini_loaded_file() ?: 'N/A',
            'scanned_ini_dirs' => php_ini_scanned_files() ?: '(none)',
            'loaded_extensions_count' => count(get_loaded_extensions()),
            'precision' => ini_get('precision'),
            'default_charset' => ini_get('default_charset'),
        ];
    }

    public function getOsInfo(): array
    {
        return [
            'os' => PHP_OS,
            'os_family' => PHP_OS_FAMILY,
            'arch' => php_uname('m'),
            'hostname' => gethostname(),
            'kernel' => php_uname('r'),
            'full' => php_uname(),
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
                'opcache.enable_cli',
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
                $val = ini_get($key);
                if ($val !== false) {
                    $config[str_replace('opcache.', '', $key)] = $val;
                }
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
                    'used_memory_raw' => $usedMemory,
                    'free_memory_raw' => $freeMemory,
                    'wasted_memory_raw' => $wastedMemory,
                    'total_memory_raw' => $totalMemory,
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
            'openssl',
            'fileinfo',
            'dom',
            'json',
            'tokenizer',
            'ctype',
        ];

        $optional = [
            'redis',
            'memcached',
            'xdebug',
            'pcov',
            'sockets',
            'ldap',
            'soap',
            'xsl',
            'exif',
            'apcu',
            'imagick',
            'intl',
        ];

        $extensions = [];
        foreach ($required as $ext) {
            $extensions[] = [
                'name' => $ext,
                'loaded' => extension_loaded($ext),
                'required' => true,
            ];
        }
        foreach ($optional as $ext) {
            $loaded = extension_loaded($ext);
            if ($loaded || in_array($ext, ['redis', 'xdebug', 'sockets', 'imagick'], true)) {
                $extensions[] = [
                    'name' => $ext,
                    'loaded' => $loaded,
                    'required' => false,
                ];
            }
        }

        return $extensions;
    }

    public function getDatabaseInfo(): array
    {
        $connection = config('database.default');
        $driver = config('database.connections.'.$connection.'.driver', 'N/A');
        $name = config('database.connections.'.$connection.'.database', 'N/A');

        $version = 'N/A';
        try {
            if ($driver === 'sqlite') {
                $result = DB::select('SELECT sqlite_version() AS version');
                $version = $result[0]->version ?? 'N/A';
            } else {
                $result = DB::select('SELECT VERSION()');
                $version = $result[0]->{'VERSION()'} ?? $result[0]->version ?? 'N/A';
            }
        } catch (\Exception) {
        }

        $size = 'N/A';
        try {
            $size = $this->getDbSize();
        } catch (\Exception) {
        }

        $tableCount = 0;
        try {
            if ($driver === 'mysql') {
                $tableCount = DB::select('SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = ?', [$name])[0]->cnt ?? 0;
            } elseif ($driver === 'sqlite') {
                $tableCount = DB::select("SELECT COUNT(*) AS cnt FROM sqlite_master WHERE type='table'")[0]->cnt ?? 0;
            }
        } catch (\Exception) {
        }

        return [
            'connection' => $connection,
            'driver' => $driver,
            'name' => $name,
            'version' => $version,
            'version_ok' => $driver === 'sqlite' || version_compare($version, '5.7.0', '>='),
            'size' => $size,
            'table_count' => $tableCount,
            'charset' => config('database.connections.'.$connection.'.charset', 'utf8mb4'),
            'collation' => config('database.connections.'.$connection.'.collation', 'utf8mb4_unicode_ci'),
        ];
    }

    public function getServerInfo(): array
    {
        return [
            'software' => request()->server('SERVER_SOFTWARE', 'N/A'),
            'is_https' => request()->secure(),
            'document_root' => request()->server('DOCUMENT_ROOT', 'N/A'),
            'server_ip' => request()->server('SERVER_ADDR')
                ?? (gethostbyname(gethostname()) ?: 'N/A'),
            'server_protocol' => request()->server('SERVER_PROTOCOL', 'N/A'),
            'http_host' => request()->server('HTTP_HOST', 'N/A'),
            'server_name' => request()->server('SERVER_NAME', 'N/A'),
            'remote_addr' => request()->server('REMOTE_ADDR', 'N/A'),
        ];
    }

    public function detectEnvironment(): array
    {
        $iniPath = php_ini_loaded_file() ?: '';
        $serverSoft = request()->server('SERVER_SOFTWARE', '');
        $hostLower = strtolower((string) request()->getHost());
        $iniLower = strtolower($iniPath);
        $serverLower = strtolower($serverSoft);

        $type = 'production';
        $detected = null;

        $localTools = [
            'LocalWP' => str_contains($serverLower, 'local by flywheel')
                || str_contains($serverLower, 'flywheel')
                || str_contains($iniLower, '\\local\\run'),
            'Herd' => str_contains($serverLower, 'herd') || str_contains($iniLower, 'herd'),
            'Valet' => str_contains($serverLower, 'valet') || str_contains($iniLower, 'valet'),
            'Laragon' => str_contains($serverLower, 'laragon'),
            'DDEV' => str_contains($serverLower, 'ddev') || str_contains($hostLower, '.ddev.site'),
            'XAMPP' => str_contains($serverLower, 'xampp') || str_contains($iniLower, 'xampp'),
            'WAMP' => str_contains($serverLower, 'wamp') || str_contains($iniLower, 'wamp'),
        ];

        foreach ($localTools as $tool => $matches) {
            if ($matches) {
                $type = 'local';
                $detected = $tool;
                break;
            }
        }

        // Dev-only TLDs are a reliable signal even when the server hides its identity.
        if ($detected === null
            && (str_ends_with($hostLower, '.test') || str_ends_with($hostLower, '.localhost') || $hostLower === 'localhost')) {
            $type = 'local';
            $detected = 'Unknown (dev TLD)';
        }

        if ($detected === null) {
            if (app()->environment('local')) {
                $type = 'local';
                $detected = 'Unknown';
            } else {
                $detected = match (true) {
                    str_contains($serverSoft, 'Apache') => 'Apache',
                    str_contains($serverSoft, 'LiteSpeed') => 'LiteSpeed',
                    str_contains($serverSoft, 'nginx') => 'Nginx',
                    str_contains($serverSoft, 'IIS') => 'IIS',
                    default => 'Unknown',
                };
            }
        }

        return [
            'type' => $type,
            'detected' => $detected,
            'ini_path' => $iniPath ?: 'N/A',
            'is_local' => $type === 'local',
            'is_production' => $type === 'production',
        ];
    }

    public function getRecommendations(
        array $php,
        array $extensions,
        array $server,
        array $storage,
        array $deployed,
        array $latest,
    ): array {
        $recommendations = [];
        $isProduction = app()->environment('production');

        if (! $php['version_ok']) {
            $recommendations[] = [
                'severity' => 'critical',
                'title' => 'PHP version outdated',
                'message' => 'PHP '.$php['version'].' is below the minimum 8.2.0.',
                'fix' => 'Upgrade PHP to 8.2 or higher via your hosting control panel.',
            ];
        }

        if (! $php['memory_limit_ok']) {
            $recommendations[] = [
                'severity' => 'high',
                'title' => 'Low PHP memory limit',
                'message' => 'Memory limit is '.$php['memory_limit']
                    .' ('.$php['memory_limit_mb'].'M). Minimum 128M recommended.',
                'fix' => 'Set memory_limit = 256M in php.ini.',
            ];
        }

        foreach ($extensions as $ext) {
            if ($ext['required'] && ! $ext['loaded']) {
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

        if ($deployed['exists'] && $latest['found'] && $deployed['version'] !== $latest['version']) {
            $recommendations[] = [
                'severity' => 'info',
                'title' => 'New version available',
                'message' => 'Deployed version '.$deployed['version']
                    .' is behind latest release '.$latest['version'].'.',
                'fix' => 'Run the deploy pipeline to update to the latest version.',
            ];
        }

        $configCached = app()->configurationIsCached();
        if (! $configCached && $isProduction) {
            $recommendations[] = [
                'severity' => 'info',
                'title' => 'Config not cached',
                'message' => 'Configuration is not cached in production.',
                'fix' => 'Run php artisan config:cache on deployment.',
            ];
        }

        if (! app()->routesAreCached() && $isProduction) {
            $recommendations[] = [
                'severity' => 'info',
                'title' => 'Routes not cached',
                'message' => 'Routes are not cached in production.',
                'fix' => 'Run php artisan route:cache on deployment.',
            ];
        }

        foreach ($storage['dirs'] as $dir) {
            if (! $dir['writable']) {
                $recommendations[] = [
                    'severity' => 'critical',
                    'title' => 'Directory not writable: '.$dir['path'],
                    'message' => $dir['path'].' is not writable by the web server.',
                    'fix' => 'Run: chmod -R 775 '.$dir['path'],
                ];
            }
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

        try {
            $result = DB::select(
                'SELECT ROUND(CAST(page_count * page_size AS REAL), 0) AS size_bytes
                 FROM pragma_page_count, pragma_page_size'
            );

            $bytes = (int) ($result[0]->size_bytes ?? 0);

            return $bytes > 0 ? $this->formatBytes($bytes) : 'N/A';
        } catch (\Exception) {
            return 'N/A';
        }
    }

    protected function getMysqlSize(string $database): string
    {
        try {
            $result = DB::select(
                'SELECT ROUND(SUM(data_length + index_length), 0) AS size_bytes
                 FROM information_schema.tables
                 WHERE table_schema = ?',
                [$database]
            );

            $bytes = (int) ($result[0]->size_bytes ?? 0);

            return $bytes > 0 ? $this->formatBytes($bytes) : 'N/A';
        } catch (\Exception) {
            return 'N/A';
        }
    }

    protected function getScheduleInfo(): array
    {
        $commands = Artisan::all();
        $namespaces = [
            trim(app()->getNamespace(), '\\'),
            'Nreljed\\DashboardCore',
        ];
        $scheduled = [];

        foreach ($commands as $name => $command) {
            if (! $command instanceof Command) {
                continue;
            }

            $class = $command::class;
            if (array_any($namespaces, fn (string $ns) => str_starts_with($class, $ns))) {
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

        $size = null;
        $recentErrors = 0;
        if ($path && file_exists($path)) {
            $size = $this->formatBytes(filesize($path));
            $recentErrors = count(preg_grep('/\.(ERROR|CRITICAL|ALERT|EMERGENCY)\b/', $this->tailFile($path, 500)));
        }

        $logFiles = [];
        $logDir = storage_path('logs');
        if (is_dir($logDir)) {
            $files = glob($logDir.'/*.log');
            $totalSize = 0;
            foreach ($files as $file) {
                $fs = filesize($file);
                $totalSize += $fs;
                $logFiles[] = [
                    'name' => basename($file),
                    'size' => $this->formatBytes($fs),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                ];
            }
            usort($logFiles, fn ($a, $b) => strcmp($a['name'], $b['name']));
        }

        return [
            'channel' => $channel,
            'level' => $level,
            'driver' => $driver,
            'path' => $path,
            'size' => $size,
            'recent_errors' => $recentErrors,
            'files' => $logFiles,
        ];
    }

    /**
     * Read the last N non-empty lines of a file without loading it fully into memory.
     *
     * @return string[]
     */
    protected function tailFile(string $path, int $lines): array
    {
        try {
            $handle = fopen($path, 'rb');
            if (! $handle) {
                return [];
            }

            $size = filesize($path);
            $chunkSize = 8192;
            $offset = $size;
            $buffer = '';
            $wanted = $lines;

            while ($offset > 0 && $wanted > 0 && strlen($buffer) < 2_097_152) {
                $readSize = min($chunkSize, $offset);
                $offset -= $readSize;
                fseek($handle, $offset);
                $buffer = fread($handle, $readSize).$buffer;
                $wanted -= substr_count($buffer, "\n");
            }

            fclose($handle);

            $split = preg_split("/\r?\n/", rtrim($buffer));
            if (! is_array($split)) {
                return [];
            }

            $split = array_values(array_filter($split, static fn ($line) => $line !== ''));

            return count($split) > $lines ? array_slice($split, -$lines) : $split;
        } catch (\Exception) {
            return [];
        }
    }

    protected function getDebugAccessUrl(): ?string
    {
        $appKey = config('app.key');
        if (! $appKey) {
            return null;
        }
        $key = substr(hash('sha256', $appKey.'debug-access'), 0, 16);

        return url('/debug.php?key='.$key);
    }

    protected function getDeployedVersion(): array
    {
        $versionFile = base_path('.deployed-version');
        if (! file_exists($versionFile)) {
            return ['exists' => false];
        }
        $version = trim(file_get_contents($versionFile));

        return [
            'exists' => true,
            'version' => $version,
            'file_path' => $versionFile,
        ];
    }

    protected function getLatestVersion(): array
    {
        $cacheKey = 'system_health_latest_version';

        return Cache::remember($cacheKey, now()->addMinutes(5), function () {
            [$repo, $token] = $this->githubConfig();

            if ($token === null) {
                return [
                    'found' => false,
                    'skipped' => true,
                    'message' => 'No GITHUB_TOKEN configured; skipped release check.',
                ];
            }

            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'Authorization' => 'Bearer '.$token,
                ])
                    ->timeout(5)
                    ->get("https://api.github.com/repos/{$repo}/releases/latest");

                if ($response->successful()) {
                    $data = $response->json();

                    return [
                        'found' => true,
                        'version' => $data['tag_name'] ?? 'unknown',
                        'url' => $data['html_url'] ?? null,
                        'published_at' => $data['published_at'] ?? null,
                        'name' => $data['name'] ?? null,
                        'body' => $data['body'] ?? null,
                    ];
                }

                return ['found' => false, 'error' => 'HTTP '.$response->status()];
            } catch (\Exception $e) {
                return ['found' => false, 'error' => $e->getMessage()];
            }
        });
    }

    /**
     * Resolve the GitHub repo and token, mirroring the DeployController pattern
     * of reading the raw .env file (survives config caching).
     *
     * @return array{0: string, 1: ?string}
     */
    protected function githubConfig(): array
    {
        $repo = env('GITHUB_REPO', 'nreljed/laravel');
        $token = env('GITHUB_TOKEN');

        $envPath = base_path('.env');
        if ($token === null && file_exists($envPath)) {
            foreach (file($envPath) as $line) {
                if (str_starts_with(trim($line), 'GITHUB_TOKEN=')) {
                    $token = trim(substr(trim($line), strlen('GITHUB_TOKEN=')));
                    $token = trim($token, '"\'');
                }
            }
        }

        return [$repo, $token === '' ? null : $token];
    }

    protected function getBuildInfo(): array
    {
        $path = public_path('build/manifest.json');

        if (! file_exists($path)) {
            return ['exists' => false];
        }

        $modified = filemtime($path);
        $ageSeconds = max(0, time() - $modified);

        return [
            'exists' => true,
            'modified_at' => date('Y-m-d H:i:s', $modified),
            'modified_timestamp' => $modified,
            'age' => floor($ageSeconds / 86400).' days',
            'age_hours' => round($ageSeconds / 3600, 1),
        ];
    }

    /**
     * Resolve the installed dashboard-core package commit and version.
     *
     * Delegates to CoreVersionService, which prefers the build-time recorded
     * storage/app/core.json and falls back to the legacy core.sha marker.
     */
    protected function getCorePackage(): array
    {
        $info = app(CoreVersionService::class)->info();

        return [
            'name' => $info['name'],
            'sha' => $info['sha'],
            'short' => $info['sha'] !== null ? substr($info['sha'], 0, 7) : null,
            'url' => $info['sha'] !== null ? 'https://github.com/nreljed/laravel/commit/'.$info['sha'] : null,
            'version' => $info['version'],
        ];
    }

    protected function getStorageInfo(): array
    {
        $dirs = [
            ['name' => 'storage/app', 'path' => storage_path('app')],
            ['name' => 'storage/framework/cache', 'path' => storage_path('framework/cache')],
            ['name' => 'storage/framework/sessions', 'path' => storage_path('framework/sessions')],
            ['name' => 'storage/framework/views', 'path' => storage_path('framework/views')],
            ['name' => 'storage/logs', 'path' => storage_path('logs')],
            ['name' => 'bootstrap/cache', 'path' => base_path('bootstrap/cache')],
            ['name' => 'public/build', 'path' => public_path('build')],
        ];

        $results = [];
        $totalSize = 0;
        foreach ($dirs as $dir) {
            $exists = is_dir($dir['path']);
            $writable = $exists && is_writable($dir['path']);
            $size = 0;
            $fileCount = 0;

            if ($exists) {
                try {
                    $rii = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($dir['path'], \RecursiveDirectoryIterator::SKIP_DOTS)
                    );
                    foreach ($rii as $file) {
                        $fileCount++;
                        $size += $file->getSize();
                    }
                } catch (\Exception) {
                }
                $totalSize += $size;
            }

            $results[] = [
                'name' => $dir['name'],
                'exists' => $exists,
                'writable' => $writable,
                'file_count' => $fileCount,
                'size' => $size > 0 ? $this->formatBytes($size) : '0 B',
            ];
        }

        return [
            'dirs' => $results,
            'total_size' => $totalSize > 0 ? $this->formatBytes($totalSize) : '0 B',
            'disk' => $this->getDiskUsage(),
        ];
    }

    protected function getDiskUsage(): ?array
    {
        $root = base_path();
        $total = @disk_total_space($root);
        $free = @disk_free_space($root);

        if ($total === false || $free === false || $total <= 0) {
            return null;
        }

        $used = max(0, $total - $free);
        $totalBytes = (int) $total;
        $freeBytes = (int) $free;

        return [
            'total' => $this->formatBytes($totalBytes),
            'free' => $this->formatBytes($freeBytes),
            'used' => $this->formatBytes((int) $used),
            'percent' => round(($used / $total) * 100, 1),
            'writable' => is_writable($root),
        ];
    }

    protected function getLaravelCacheStatus(): array
    {
        return [
            'config_cached' => app()->configurationIsCached(),
            'routes_cached' => app()->routesAreCached(),
            'events_cached' => app()->eventsAreCached(),
            'config_cache_path' => app()->getCachedConfigPath(),
            'routes_cache_path' => app()->getCachedRoutesPath(),
            'is_down_for_maintenance' => app()->isDownForMaintenance(),
        ];
    }
}
