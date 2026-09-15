<?php

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterFacades;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Http\Request;

/**
 * Laravel Deployment Health Check
 *
 * A self-contained diagnostic tool for verifying Laravel deployments.
 * Drop this file in your public/ directory and visit /debug.php?key=health
 *
 * Usage:
 *   /debug.php?key=health    — Show all diagnostic info
 *   /debug.php               — Show non-sensitive info only
 *   /debug.php?key=health&json=1 — Machine-readable JSON output
 *
 * Reusable for any Laravel project — just copy public/debug.php to the new project.
 */

// ---- Configuration ----
\error_reporting(E_ALL);
\ini_set('display_errors', '1');

// Convert PHP warnings/notices to exceptions so existing try/catch blocks catch them
\set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Catch fatal errors that bypass try/catch
\register_shutdown_function(function () {
    $error = \error_get_last();
    if ($error !== null && \in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $msg = "=== FATAL ERROR ===\nType: {$error['type']}\nMessage: {$error['message']}\nFile: {$error['file']}\nLine: {$error['line']}\n";
        // Log to file in case browser output fails
        @\file_put_contents(\dirname(__DIR__).'/storage/logs/debug-fatal.log', $msg, FILE_APPEND);
        // Also try to flush to browser
        if (! \headers_sent()) {
            \header('Content-Type: text/plain; charset=utf-8');
        }
        echo $msg;
    }
});

$appRoot = \dirname(__DIR__);

// ---- Security (derive key from APP_KEY so it's unique per installation) ----
$envPath = $appRoot.'/.env';
$accessKey = 'health'; // fallback
if (\file_exists($envPath)) {
    $envRaw = \file_get_contents($envPath);
    if (\preg_match('/^APP_KEY\s*=\s*"?([^"\r\n]+)"?\s*$/m', $envRaw, $m)) {
        $accessKey = \substr(\hash('sha256', $m[1].'debug-access'), 0, 16);
    }
}
$authenticated = isset($_GET['key']) && $_GET['key'] === $accessKey;
$jsonMode = isset($_GET['json']) && $_GET['json'] === '1';

// ---- Collectors ----
$results = [
    'status' => 'running',
    'timestamp' => \date('Y-m-d H:i:s'),
    'checks' => [],
];

function check(string $group, string $label, bool $passed, ?string $detail = null): bool
{
    global $results;
    $results['checks'][] = [
        'group' => $group,
        'label' => $label,
        'passed' => $passed,
        'detail' => $detail,
    ];

    return $passed;
}

// ===================================================================
// 1. PHP & Server Info
// ===================================================================
check('system', 'PHP Version', true, PHP_VERSION);
check('system', 'Server Software', true, $_SERVER['SERVER_SOFTWARE'] ?? 'unknown');
check('system', 'Server Protocol', true, $_SERVER['SERVER_PROTOCOL'] ?? 'unknown');
check('system', 'HTTP Host', true, $_SERVER['HTTP_HOST'] ?? 'unknown');
check('system', 'Document Root', true, $_SERVER['DOCUMENT_ROOT'] ?? 'unknown');
check('system', 'PHP SAPI', true, PHP_SAPI);
check('system', 'PHP OS', true, PHP_OS);
check('system', 'Memory Limit', true, \ini_get('memory_limit'));
check('system', 'Max Execution Time', true, \ini_get('max_execution_time').'s');
check('system', 'Upload Max Filesize', true, \ini_get('upload_max_filesize'));
check('system', 'Post Max Size', true, \ini_get('post_max_size'));

// ===================================================================
// 2. PHP Configuration (authenticated only)
// ===================================================================
if ($authenticated) {
    check('php_config', 'display_errors', true, \ini_get('display_errors'));
    check('php_config', 'error_reporting', true, \ini_get('error_reporting'));
    check('php_config', 'output_buffering', true, \ini_get('output_buffering') ?: 'Off');
    check('php_config', 'implicit_flush', true, \ini_get('implicit_flush') ?: 'Off');

    $opcacheEnabled = \function_exists('opcache_get_status') && \opcache_get_status(false);
    check('php_config', 'Opcache Enabled', (bool) $opcacheEnabled, $opcacheEnabled ? 'Yes' : 'No');

    if ($opcacheEnabled) {
        $opcacheConfig = \opcache_get_configuration();
        $validateTimestamps = $opcacheConfig['directives']['opcache.validate_timestamps'] ?? 'unknown';
        $revalidateFreq = $opcacheConfig['directives']['opcache.revalidate_freq'] ?? 'unknown';
        check('php_config', 'Opcache validate_timestamps', true, (string) $validateTimestamps);
        check('php_config', 'Opcache revalidate_freq', true, (string) $revalidateFreq.'s');
    }
}

// ===================================================================
// 3. Key PHP Extensions
// ===================================================================
$requiredExts = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'gd', 'fileinfo', 'bcmath', 'zip', 'openssl', 'xml', 'tokenizer', 'ctype', 'curl'];
foreach ($requiredExts as $ext) {
    check('extensions', "Extension: $ext", \extension_loaded($ext));
}

// ===================================================================
// 4. App Root & Critical Paths
// ===================================================================
check('filesystem', 'App Root', \is_dir($appRoot), $appRoot);

$paths = [
    'public/index.php' => 'file',
    'artisan' => 'file',
    '.env' => 'file',
    'vendor/autoload.php' => 'file',
    'vendor/composer/autoload_real.php' => 'file',
    'vendor/composer/autoload_classmap.php' => 'file',
    'vendor/composer/autoload_psr4.php' => 'file',
    'vendor/composer/autoload_namespaces.php' => 'file',
    'vendor/composer/autoload_static.php' => 'file',
    'vendor/composer/InstalledVersions.php' => 'file',
    'vendor/composer/installed.json' => 'file',
    'vendor/symfony/polyfill-mbstring/bootstrap.php' => 'file',
    'vendor/symfony/deprecation-contracts/function.php' => 'file',
    'vendor/symfony/polyfill-php83/Resources/stubs/Override.php' => 'file',
    'storage' => 'dir',
    'storage/logs' => 'dir',
    'storage/framework' => 'dir',
    'storage/framework/views' => 'dir',
    'storage/framework/cache' => 'dir',
    'storage/framework/cache/data' => 'dir',
    'storage/framework/sessions' => 'dir',
    'storage/framework/testing' => 'dir',
    'bootstrap/cache' => 'dir',
    'public/build/manifest.json' => 'file',
    'public/.htaccess' => 'file',
    'bootstrap/app.php' => 'file',
    'config/app.php' => 'file',
];

foreach ($paths as $relPath => $type) {
    $fullPath = $appRoot.'/'.$relPath;
    $exists = $type === 'dir' ? \is_dir($fullPath) : \file_exists($fullPath);
    $label = $type === 'dir' ? "Directory: $relPath" : "File: $relPath";
    $detail = null;
    if ($authenticated && $exists) {
        $detail = \realpath($fullPath);
        if ($type === 'file') {
            $detail .= ' ('.\round(\filesize($fullPath) / 1024, 1).' KB)';
        }
    }
    check('filesystem', $label, $exists, $detail);
}

// ===================================================================
// 5. Vendor Integrity Deep Scan (authenticated only)
// ===================================================================
if ($authenticated) {
    $criticalClasses = [
        'Illuminate\Foundation\Application' => 'laravel/framework/src/Illuminate/Foundation/Application.php',
        'Illuminate\Foundation\Http\Kernel' => 'laravel/framework/src/Illuminate/Foundation/Http/Kernel.php',
        'Illuminate\Http\Request' => 'laravel/framework/src/Illuminate/Http/Request.php',
        'Illuminate\Support\Facades\Route' => 'laravel/framework/src/Illuminate/Support/Facades/Route.php',
        'Illuminate\Support\Facades\App' => 'laravel/framework/src/Illuminate/Support/Facades/App.php',
        'Symfony\Component\HttpFoundation\Response' => 'symfony/http-foundation/Response.php',
        'Inertia\Inertia' => 'inertiajs/inertia-laravel/src/Inertia.php',
    ];

    foreach ($criticalClasses as $className => $relPath) {
        $fullPath = $appRoot.'/vendor/'.$relPath;
        $exists = \file_exists($fullPath);
        check('vendor', "Class: $className", $exists, $exists ? \realpath($fullPath) : 'MISSING: vendor/'.$relPath);
    }

    // Check for orphaned autoload entries in classmap
    try {
        $classmapPath = $appRoot.'/vendor/composer/autoload_classmap.php';
        if (\file_exists($classmapPath)) {
            $classmap = require $classmapPath;
            $checked = 0;
            $missing = 0;
            $sampleMissing = [];
            if (\is_array($classmap)) {
                foreach ($classmap as $class => $path) {
                    if ($checked >= 2000) {
                        break;
                    }
                    $checked++;
                    if (! \file_exists($path)) {
                        $missing++;
                        if (\count($sampleMissing) < 5) {
                            $sampleMissing[] = "$class => $path";
                        }
                    }
                }
            }
            if ($missing > 0) {
                check('vendor', "Classmap missing files ($missing of $checked checked)", false,
                    'Sample: '.\implode('; ', $sampleMissing));
            } else {
                check('vendor', "Classmap integrity ($checked checked)", true, 'All files present');
            }
        } else {
            check('vendor', 'Classmap file', false, 'vendor/composer/autoload_classmap.php not found');
        }
    } catch (Throwable $e) {
        check('vendor', 'Classmap integrity', false, 'Error: '.$e->getMessage());
    }
}

// ===================================================================
// 6. Directory Permissions (authenticated only)
// ===================================================================
if ($authenticated) {
    $permPaths = [
        'storage',
        'storage/logs',
        'storage/framework',
        'storage/framework/views',
        'storage/framework/cache',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'bootstrap/cache',
    ];
    foreach ($permPaths as $relPath) {
        $fullPath = $appRoot.'/'.$relPath;
        if (\is_dir($fullPath)) {
            $perms = \substr(\sprintf('%o', \fileperms($fullPath)), -4);
            $writable = \is_writable($fullPath);
            check('permissions', "Permissions: $relPath", $writable, "mode=$perms writable=".($writable ? 'yes' : 'no'));
        } else {
            check('permissions', "Permissions: $relPath", false, 'Directory does not exist');
        }
    }
}

// ===================================================================
// 7. .env File (authenticated only)
// ===================================================================
if ($authenticated) {
    $envPath = $appRoot.'/.env';
    if (\file_exists($envPath)) {
        check('env', '.env exists', true, 'Found at '.\realpath($envPath));
        $envContent = \file_get_contents($envPath);
        $envVars = [];
        foreach (\explode("\n", $envContent) as $line) {
            $line = \trim($line);
            if ($line === '' || \str_starts_with($line, '#')) {
                continue;
            }
            if (\str_contains($line, '=')) {
                [$key, $val] = \explode('=', $line, 2);
                $key = \trim($key);
                $val = \trim($val);
                // Strip surrounding double quotes (Laravel .env supports quoted values)
                if (\str_starts_with($val, '"') && \str_ends_with($val, '"')) {
                    $val = \substr($val, 1, -1);
                }
                if (\in_array($key, ['APP_KEY', 'DB_PASSWORD', 'DEPLOY_TOKEN', 'GITHUB_TOKEN'])) {
                    $val = \strlen($val) > 8 ? \substr($val, 0, 4).'****'.\substr($val, -4) : '****';
                }
                $envVars[$key] = $val;
            }
        }
        // Store raw values for format validation (before masking)
        $envRaw = \file_get_contents($envPath);
        $rawKey = '';
        foreach (\explode("\n", $envRaw) as $line) {
            $line = \trim($line);
            if ($line === '' || \str_starts_with($line, '#')) {
                continue;
            }
            if (\str_starts_with($line, 'APP_KEY=')) {
                $rawKey = \substr($line, 8);
            } elseif (\preg_match('/^APP_KEY\s*=\s*/', $line)) {
                $rawKey = \preg_replace('/^APP_KEY\s*=\s*/', '', $line);
            } else {
                continue;
            }
            // Strip surrounding double quotes (Laravel .env supports quoted values)
            if (\str_starts_with($rawKey, '"') && \str_ends_with($rawKey, '"')) {
                $rawKey = \substr($rawKey, 1, -1);
            }
            break;
        }
        $requiredEnv = ['APP_KEY', 'APP_ENV', 'APP_DEBUG', 'APP_URL', 'DB_CONNECTION', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME'];
        foreach ($requiredEnv as $key) {
            $present = isset($envVars[$key]);
            check('env', "ENV: $key", $present, $present ? $envVars[$key] : 'MISSING');
        }
        check('env', 'ENV: DB_PASSWORD', isset($envVars['DB_PASSWORD']), 'present');
        check('env', 'ENV: APP_KEY has valid format',
            $rawKey !== '' && \str_starts_with($rawKey, 'base64:'),
            $envVars['APP_KEY'] ?? 'MISSING');
    } else {
        check('env', '.env file', false, 'NOT FOUND at '.$envPath);
    }
}

// ===================================================================
// 8. Vendor Autoload Test
// ===================================================================
$autoloadPath = $appRoot.'/vendor/autoload.php';
if (\file_exists($autoloadPath)) {
    try {
        $loader = require $autoloadPath;
        check('autoload', 'vendor/autoload.php', true, 'Loaded successfully');
        // Verify autoloader finds critical classes
        $classesToCheck = [
            '\Illuminate\Support\Facades\App',
            '\Symfony\Component\HttpFoundation\Request',
            '\Illuminate\Contracts\Http\Kernel',
        ];
        foreach ($classesToCheck as $cls) {
            if (\class_exists($cls) || \interface_exists($cls)) {
                check('autoload', 'Class autoload: '.$cls, true);
            } else {
                check('autoload', 'Class autoload: '.$cls, false, 'Class not found by autoloader');
            }
        }
    } catch (Throwable $e) {
        check('autoload', 'vendor/autoload.php', false, 'Error: '.$e->getMessage());
    }
} else {
    check('autoload', 'vendor/autoload.php', false, 'File does not exist');
}

// ===================================================================
// 8.5. .env Parser Test (uses actual Dotenv library, pre-bootstrap)
// ===================================================================
if ($authenticated && \file_exists($autoloadPath) && \class_exists('Dotenv\Dotenv')) {
    $envPathForParser = $appRoot.'/.env';
    if (\file_exists($envPathForParser)) {
        try {
            $dotenv = Dotenv::createImmutable($appRoot);
            // safeLoad() catches InvalidFileException gracefully — but we want to see it
            // Use load() instead to throw on invalid format
            $dotenv->load();
            check('env_parser', '.env parsed successfully', true, 'No syntax errors');
        } catch (InvalidFileException $e) {
            check('env_parser', '.env parsing failed', false,
                'InvalidFileException: '.$e->getMessage());
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            // Detect the specific infinite-recursion-on-unquoted-value pattern we hit
            if (\str_contains($msg, 'Allowed memory size') && \str_contains($msg, 'Dotenv\\Parser')) {
                check('env_parser', '.env parsing failed', false,
                    'FATAL: Dotenv parser crashed (likely unquoted multi-word value in .env). '
                    .'Check all values with spaces/unicode are wrapped in double quotes, e.g.: APP_NAME="My App"');
            } else {
                check('env_parser', '.env parsing failed', false,
                    \get_class($e).': '.$msg);
            }
        }
    } else {
        check('env_parser', '.env file', false, 'Not found at '.$envPathForParser);
    }
} elseif ($authenticated && ! \file_exists($autoloadPath)) {
    check('env_parser', '.env parser test', false, 'Skipped — vendor autoloader missing');
} elseif ($authenticated && ! \class_exists('Dotenv\Dotenv')) {
    check('env_parser', '.env parser test', false, 'Skipped — Dotenv class not found (check vendor)');
}

// ===================================================================
// 9. Laravel Bootstrap — Step-by-Step Trace + Full Kernel Handle Test (authenticated only)
// ===================================================================
if ($authenticated && \file_exists($autoloadPath)) {
    $bootstrapOk = false;
    $bootstrappers = [];

    // ---- Step 0: Create Application (proves bootstrap/app.php loads) ----
    try {
        $app = require_once $appRoot.'/bootstrap/app.php';
        if (! $app instanceof Application) {
            check('laravel', 'Application creation', false,
                'bootstrap/app.php did not return an Application instance');
        } else {
            check('laravel', 'Application creation', true, 'Laravel '.$app::VERSION);
            $bootstrapOk = true;
        }
    } catch (Throwable $e) {
        check('laravel', 'Application creation', false,
            \get_class($e).': '.$e->getMessage());
        @\file_put_contents(\dirname(__DIR__).'/storage/logs/debug-laravel.log',
            \date('Y-m-d H:i:s').' bootstrap/app.php: '.$e->getMessage().
            ' in '.$e->getFile().':'.$e->getLine()."\n", FILE_APPEND);
    }

    if ($bootstrapOk) {

        // ---- Step-by-step Bootstrap Trace ----
        // Test each Laravel bootstrapper individually to pinpoint failures
        $bootstrappers = [
            'LoadEnvironmentVariables' => LoadEnvironmentVariables::class,
            'LoadConfiguration' => LoadConfiguration::class,
            'HandleExceptions' => HandleExceptions::class,
            'RegisterFacades' => RegisterFacades::class,
            'RegisterProviders' => RegisterProviders::class,
            'BootProviders' => BootProviders::class,
        ];

        $bootstrapFailed = false;
        foreach ($bootstrappers as $stepName => $bootClass) {
            if ($bootstrapFailed) {
                check('laravel_step', "Bootstrap: $stepName", false, 'Skipped (previous step failed)');

                continue;
            }
            try {
                $app->bootstrapWith([$bootClass]);
                check('laravel_step', "Bootstrap: $stepName", true);
            } catch (InvalidFileException $e) {
                check('laravel_step', "Bootstrap: $stepName", false,
                    'InvalidFileException: '.$e->getMessage());
                $bootstrapFailed = true;
            } catch (BindingResolutionException $e) {
                check('laravel_step', "Bootstrap: $stepName", false,
                    'BindingResolutionException: '.$e->getMessage());
                $bootstrapFailed = true;
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                $checkMsg = \get_class($e).': '.$msg;

                // Specific error pattern detection
                if (\str_contains($msg, 'Allowed memory size') && \str_contains($msg, 'Dotenv\\Parser')) {
                    $checkMsg .= ' [ACTION: Check .env has all multi-word values in quotes, e.g. APP_NAME="My App"]';
                } elseif (\preg_match('/Base table.*not found|SQLSTATE|sessions/', $msg)) {
                    $checkMsg .= ' [ACTION: Run php artisan migrate to create missing tables]';
                } elseif (\str_contains($msg, 'file_put_contents') || \str_contains($msg, 'failed to open stream')) {
                    $checkMsg .= ' [ACTION: Check storage/ directory permissions (writable)]';
                } elseif (\str_contains($msg, 'Access denied') || \str_contains($msg, 'SQLSTATE[HY000]')) {
                    $checkMsg .= ' [ACTION: Check DB credentials in .env]';
                } elseif (\str_contains($msg, ' Class ') && \str_contains($msg, ' not found')) {
                    $checkMsg .= ' [ACTION: Run composer dump-autoload or check vendor directory]';
                }

                check('laravel_step', "Bootstrap: $stepName", false, $checkMsg);
                $bootstrapFailed = true;
            }
        }

        // ---- Full Kernel handle() Test (skips bootstrap since already bootstrapped) ----
        if (! $bootstrapFailed) {
            try {
                $obLevel = \ob_get_level();

                $testRequest = Request::create('/', 'GET');
                /** @var KernelContract $kernel */
                $kernel = $app->make(KernelContract::class);

                $response = $kernel->handle($testRequest);

                check('laravel', 'Environment', true, $app->environment());
                check('laravel', 'Debug Mode', true,
                    $app->hasDebugModeEnabled() ? 'ENABLED' : 'DISABLED');

                $statusCode = $response->getStatusCode();
                $content = $response->getContent();
                $contentLength = $content !== null ? \strlen($content) : 0;

                check('laravel', 'Kernel handle() — Status',
                    $statusCode >= 200 && $statusCode < 500, "HTTP $statusCode");

                $detail = "Content-Length: $contentLength bytes";
                if ($contentLength > 0) {
                    $preview = \strip_tags($content);
                    $preview = \strlen($preview) > 300
                        ? \substr($preview, 0, 300).'...' : $preview;
                    $detail .= ' — Preview: '.$preview;
                } else {
                    $detail .= ' — EMPTY RESPONSE BODY';
                }
                check('laravel', 'Kernel handle() — Content', $contentLength > 0, $detail);

                // Pattern-match known error responses
                if ($contentLength > 0 && $statusCode >= 400) {
                    if (\str_contains($content, 'Base table or view not found') ||
                        \str_contains($content, 'SQLSTATE') ||
                        \str_contains($content, 'sessions')) {
                        check('laravel', '→ Likely session/database issue', false,
                            'Run: php artisan migrate');
                    } elseif (\str_contains($content, 'file_put_contents') ||
                        \str_contains($content, 'failed to open stream')) {
                        check('laravel', '→ Likely storage permissions', false,
                            'Check storage/ and bootstrap/cache are writable');
                    }
                }

                $kernel->terminate($testRequest, $response);

                while (\ob_get_level() > $obLevel) {
                    \ob_end_clean();
                }

            } catch (Throwable $e) {
                check('laravel', 'Kernel handle()', false,
                    \get_class($e).': '.$e->getMessage().
                    ' in '.$e->getFile().':'.$e->getLine());

                try {
                    check('laravel', 'Environment', true, $app->environment());
                } catch (Throwable) {
                    check('laravel', 'Environment', false, 'Not available');
                }
                try {
                    check('laravel', 'Debug Mode', true,
                        $app->hasDebugModeEnabled() ? 'ENABLED' : 'DISABLED');
                } catch (Throwable) {
                    check('laravel', 'Debug Mode', false, 'Not available');
                }

                $msg = $e->getMessage();
                if (\preg_match('/Base table.*not found|SQLSTATE|sessions/', $msg)) {
                    check('laravel', '→ Likely database issue', false,
                        'Missing tables — run: php artisan migrate');
                } elseif (\str_contains($msg, 'file_put_contents') ||
                    \str_contains($msg, 'failed to open stream')) {
                    check('laravel', '→ Likely storage permissions', false,
                        'Check storage/ directory is writable');
                }
            }
        } else {
            check('laravel', 'Kernel handle()', false,
                'Skipped — bootstrap did not complete');
        }

        // ---- Service Provider Check ----
        try {
            $providers = $app->getLoadedProviders();
            $totalProviders = \count($providers);
            $bootedProviders = \count(\array_filter($providers));
            check('laravel', "Service providers ($totalProviders loaded, $bootedProviders booted)",
                $totalProviders > 0, "$totalProviders registered");

            $expectedProviders = [
                'App\Providers\AppServiceProvider',
                'Illuminate\Foundation\Providers\FoundationServiceProvider',
            ];
            foreach ($expectedProviders as $pClass) {
                check('laravel', "Provider: $pClass",
                    isset($providers[$pClass]) && $providers[$pClass],
                    isset($providers[$pClass])
                        ? ($providers[$pClass] ? 'booted' : 'registered but not booted')
                        : 'NOT FOUND');
            }
        } catch (Throwable $e) {
            check('laravel', 'Service provider check', false, $e->getMessage());
        }
    } // end if ($bootstrapOk)
} // end if ($authenticated)

// ===================================================================
// 10. Database Connection (authenticated only)
// ===================================================================
if ($authenticated && \file_exists($autoloadPath)) {
    $dbDriver = 'unknown';
    try {
        $envContent = \file_exists($appRoot.'/.env') ? \file_get_contents($appRoot.'/.env') : '';
        \preg_match('/DB_CONNECTION=(.+)/', $envContent, $m);
        $dbDriver = \trim($m[1] ?? 'mysql');
        \preg_match('/DB_HOST=(.+)/', $envContent, $m);
        $dbHost = \trim($m[1] ?? '127.0.0.1');
        \preg_match('/DB_PORT=(.+)/', $envContent, $m);
        $dbPort = \trim($m[1] ?? '3306');
        \preg_match('/DB_DATABASE=(.+)/', $envContent, $m);
        $dbName = \trim($m[1] ?? '');
        \preg_match('/DB_USERNAME=(.+)/', $envContent, $m);
        $dbUser = \trim($m[1] ?? '');
        \preg_match('/DB_PASSWORD=(.+)/', $envContent, $m);
        $dbPass = \trim($m[1] ?? '');

        if ($dbDriver === 'sqlite') {
            check('database', 'DB Driver', true, 'SQLite');
            if (isset($dbName) && \file_exists($dbName)) {
                check('database', 'SQLite File', true, $dbName);
            } else {
                check('database', 'SQLite File', false, 'Not found: '.($dbName ?? 'unknown'));
            }
        } elseif (\in_array($dbDriver, ['mysql', 'mariadb'])) {
            if (\extension_loaded('pdo_mysql')) {
                try {
                    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName";
                    // Use the discovered credentials — no empty password fallback
                    if ($dbPass === '' || $dbPass === null) {
                        check('database', "MySQL: $dbName @ $dbHost:$dbPort", false,
                            'DB_PASSWORD is empty — check .env configuration');
                    } else {
                        $pdo = new PDO($dsn, $dbUser, $dbPass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_TIMEOUT => 3,
                        ]);
                        $serverVer = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
                        check('database', "MySQL: $dbName @ $dbHost:$dbPort", true, "Connected! Server: $serverVer");
                    }
                } catch (PDOException $e) {
                    $errorMsg = $e->getMessage();
                    check('database', "MySQL: $dbName @ $dbHost:$dbPort", false, $errorMsg);
                    if (\str_contains($errorMsg, 'Access denied')) {
                        check('database', '→ DB credentials', false, 'Username or password is wrong');
                    } elseif (\str_contains($errorMsg, 'Unknown database')) {
                        check('database', '→ DB name', false, "Database '$dbName' does not exist");
                    } elseif (\str_contains($errorMsg, 'Connection refused')) {
                        check('database', '→ DB host', false, "Cannot reach $dbHost:$dbPort");
                    }
                }
            } else {
                check('database', 'MySQL driver', false, 'pdo_mysql extension not loaded');
            }
        } else {
            check('database', 'DB Driver', true, $dbDriver.' (not tested by this check)');
        }
    } catch (Throwable $e) {
        check('database', 'Database check', false, $e->getMessage());
    }
}

// ===================================================================
// 11. Build Assets (Vite manifest)
// ===================================================================
$manifestPath = $appRoot.'/public/build/manifest.json';
if (\file_exists($manifestPath)) {
    $manifest = \json_decode(\file_get_contents($manifestPath), true);
    $entryCount = \is_array($manifest) ? \count($manifest) : 0;
    check('assets', 'Vite manifest', $entryCount > 0, "$entryCount entries in manifest.json");
    $hasAppEntry = isset($manifest['resources/js/app.tsx']) || isset($manifest['resources/js/app.js']);
    check('assets', 'App entry point', $hasAppEntry, $hasAppEntry ? 'found' : 'MISSING');
} else {
    check('assets', 'Vite manifest', false, 'public/build/manifest.json not found — run npm run build');
    check('assets', 'App entry point', false, 'Cannot verify without manifest');
}

// ===================================================================
// 12. Cache Directory Contents
// ===================================================================
if ($authenticated) {
    $cacheDir = $appRoot.'/bootstrap/cache';
    if (\is_dir($cacheDir)) {
        $cacheFiles = \glob($cacheDir.'/*.php');
        $fileNames = \array_map(fn ($f) => \basename($f), $cacheFiles);
        check('cache', 'Cache files', true, \count($cacheFiles).' files: '.(\implode(', ', $fileNames) ?: 'empty'));
    }
}

// ===================================================================
// 13. Application Version
// ===================================================================
$versionFile = $appRoot.'/.deployed-version';
if (\file_exists($versionFile)) {
    $currentVer = \trim(\file_get_contents($versionFile));

    // Read GITHUB_TOKEN from .env for authenticated API calls
    $githubToken = '';
    if (\file_exists($envPath)) {
        $envRaw = \file_get_contents($envPath);
        if (\preg_match('/^GITHUB_TOKEN\s*=\s*"?([^"\r\n]+)"?\s*$/m', $envRaw, $m)) {
            $githubToken = \trim($m[1]);
        }
    }

    try {
        $gitHubAPI = 'https://api.github.com/repos/nreljed/laravel.shop/releases/latest';
        $headers = "User-Agent: debug.php\r\n";
        if ($githubToken !== '') {
            $headers .= "Authorization: Bearer $githubToken\r\n";
        }
        $opts = ['http' => ['method' => 'GET', 'header' => $headers, 'timeout' => 5]];
        $ctx = \stream_context_create($opts);
        $latestRaw = @\file_get_contents($gitHubAPI, false, $ctx);

        if ($latestRaw) {
            $latestData = \json_decode($latestRaw, true);
            $latestVer = $latestData['tag_name'] ?? '';
            $latestUrl = $latestData['html_url'] ?? '';
            $isLatest = $latestVer === '' || \ltrim($currentVer, 'v') === \ltrim($latestVer, 'v');
            check('version', 'Deployed version', true,
                $currentVer.($isLatest ? ' (latest)' : " — BEHIND: $latestVer available at $latestUrl"));
            if (! $isLatest) {
                check('version', 'Update needed', false, "Deploy $latestVer to catch up");
            }
        } else {
            check('version', 'Deployed version', true, $currentVer.' (GitHub API unreachable — cannot compare)');
        }
    } catch (Throwable $e) {
        check('version', 'Deployed version', true, "$currentVer (version check error: ".$e->getMessage().')');
    }
} else {
    check('version', 'Deployed version', false, '.deployed-version not found — deploy has never completed');
}

// ===================================================================
// 14. Platform Core & Modules (installed state — authoritative)
// ===================================================================

$pkgGithubToken = '';
if (\file_exists($envPath)) {
    $envRaw = \file_get_contents($envPath);
    if (\preg_match('/^GITHUB_TOKEN\s*=\s*"?([^"\r\n]+)"?\s*$/m', $envRaw, $m)) {
        $pkgGithubToken = \trim($m[1]);
    }
}

// Read the app's require constraints for nreljed/* packages
$appComposerJson = $appRoot.'/composer.json';
$requireConstraints = [];
if (\file_exists($appComposerJson)) {
    $appMeta = \json_decode((string) file_get_contents($appComposerJson), true);
    if (isset($appMeta['require'])) {
        foreach ($appMeta['require'] as $pkg => $constraint) {
            if (\str_starts_with($pkg, 'nreljed/')) {
                $requireConstraints[$pkg] = $constraint;
            }
        }
    }
}

// Authoritative install state: vendor/composer/installed.json (what Composer
// actually installed), falling back to composer.lock for any nreljed/* package
// absent from the installed list.
$installed = [];    // name => installed version
$lockRefByPkg = []; // name => commit reference
$installedJsonPath = $appRoot.'/vendor/composer/installed.json';
if (\file_exists($installedJsonPath)) {
    $instData = \json_decode((string) file_get_contents($installedJsonPath), true);
    foreach (($instData['packages'] ?? []) as $instPkg) {
        if (! isset($instPkg['name']) || ! \str_starts_with($instPkg['name'], 'nreljed/')) {
            continue;
        }
        $installed[$instPkg['name']] = $instPkg['version'] ?? null;
        $lockRefByPkg[$instPkg['name']] = $instPkg['source']['reference'] ?? ($instPkg['dist']['reference'] ?? null);
    }
}
$lockPath = $appRoot.'/composer.lock';
if (\file_exists($lockPath)) {
    $lockData = \json_decode((string) file_get_contents($lockPath), true);
    foreach (\array_merge($lockData['packages'] ?? [], $lockData['packages-dev'] ?? []) as $lockPkg) {
        if (! isset($lockPkg['name']) || ! \str_starts_with($lockPkg['name'], 'nreljed/')) {
            continue;
        }
        $installed[$lockPkg['name']] ??= $lockPkg['version'] ?? null;
        $lockRefByPkg[$lockPkg['name']] ??= $lockPkg['source']['reference'] ?? ($lockPkg['dist']['reference'] ?? null);
    }
}

// Map package names to GitHub repos (auto-discovering any nreljed/* module)
$repoMap = [
    'nreljed/platform' => 'nreljed/platform',
    'nreljed/blog' => 'nreljed/blog',
];
foreach (\array_keys($installed) as $pkg) {
    if (! isset($repoMap[$pkg])) {
        $repoMap[$pkg] = $pkg;
    }
}

// Fetch latest release for each package (with GITHUB_TOKEN when available)
$latestCache = [];
$pkgGitHubHeaders = "User-Agent: debug.php\r\nAccept: application/vnd.github+json\r\n";

// Platform Core first, then each module — same two checks per package
foreach ($repoMap as $pkg => $repo) {
    $group = $pkg === 'nreljed/platform' ? 'core' : 'modules';
    $installedVer = $installed[$pkg] ?? null;
    $constraint = $requireConstraints[$pkg] ?? null;
    $shortRef = ($lockRefByPkg[$pkg] ?? null) !== null ? \substr($lockRefByPkg[$pkg], 0, 7) : null;

    $isDev = $installedVer !== null
        && (\str_contains($installedVer, 'dev')
            || $installedVer === '@dev'
            || \str_starts_with($installedVer, '9999999'));

    if ($constraint !== null) {
        $constraintOk = $installedVer !== null && $isDev === false;
        $detail = $installedVer ?? 'NOT INSTALLED';
        if ($isDev) {
            $detail .= ' (dev build)';
        }
        check($group, "Required: $pkg", $constraintOk, "constraint: $constraint — installed: $detail");
    }

    if ($installedVer === null) {
        check($group, "Latest: $pkg", false, 'Package not installed');

        continue;
    }

    // Fetch latest release (only once per repo, with timeout + optional auth)
    if (! isset($latestCache[$repo])) {
        $latestCache[$repo] = null;
        $ghApi = "https://api.github.com/repos/$repo/releases/latest";
        $hdrs = $pkgGitHubHeaders;
        if ($pkgGithubToken !== '') {
            $hdrs .= "Authorization: Bearer $pkgGithubToken\r\n";
        }
        $opts = ['http' => ['method' => 'GET', 'header' => $hdrs, 'timeout' => 5]];
        $ctx = @\stream_context_create($opts);
        $raw = @\file_get_contents($ghApi, false, $ctx);
        if ($raw) {
            $data = \json_decode($raw, true);
            $latestCache[$repo] = $data['tag_name'] ?? null;
        }
    }

    $latestVer = $latestCache[$repo];
    $detail = "installed: $installedVer";
    if ($shortRef !== null) {
        $detail .= " (ref $shortRef)";
    }

    if ($latestVer !== null) {
        $installedNorm = \ltrim($installedVer, 'v');
        $latestNorm = \ltrim($latestVer, 'v');
        if ($installedNorm === $latestNorm) {
            $detail .= " — latest ($latestVer)";
            check($group, "Latest: $pkg", true, $detail);
        } else {
            // Semantic comparison: try version_compare, fall back to string
            $behind = \version_compare($installedNorm, $latestNorm, '<');
            $detail .= $behind
                ? " — BEHIND: $latestVer available"
                : " ahead of release $latestVer";
            check($group, "Latest: $pkg", ! $behind, $detail);
        }
    } else {
        $detail .= ' — latest: (GitHub API unreachable)';
        check($group, "Latest: $pkg", true, $detail);
    }
}

// ===================================================================
// 15. Summary
// ===================================================================
$total = \count($results['checks']);
$passed = \count(\array_filter($results['checks'], fn ($c) => $c['passed']));
$failed = $total - $passed;
$results['status'] = $failed === 0 ? 'passed' : ($passed > 0 ? 'partial' : 'failed');
$results['summary'] = "$passed/$total checks passed";

// ===================================================================
// Output
// ===================================================================
if ($jsonMode) {
    \header('Content-Type: application/json');
    echo \json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---- HTML Output ----
$red = '#dc2626';
$green = '#16a34a';
$amber = '#d97706';
$gray = '#6b7280';

\header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>Laravel Health Check</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #111827; padding: 24px; }
  .container { max-width: 960px; margin: 0 auto; }
  h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 4px; }
  .subtitle { color: #6b7280; font-size: 0.875rem; margin-bottom: 24px; }
  .status-bar { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
  .status-badge { padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.875rem; }
  .status-passed { background: #dcfce7; color: #166534; }
  .status-partial { background: #fef3c7; color: #92400e; }
  .status-failed { background: #fee2e2; color: #991b1b; }
  .group { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom: 16px; overflow: hidden; }
  .group-header { padding: 12px 16px; font-weight: 600; font-size: 0.875rem; text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid #e5e7eb; }
  .check { display: flex; align-items: flex-start; padding: 8px 16px; border-bottom: 1px solid #f3f4f6; font-size: 0.875rem; }
  .check:last-child { border-bottom: none; }
  .icon { margin-right: 10px; flex-shrink: 0; width: 18px; height: 18px; text-align: center; line-height: 18px; font-size: 14px; }
  .label { flex: 1; }
  .detail { color: #6b7280; font-size: 0.8125rem; margin-left: 8px; word-break: break-word; }
  .fail-detail { color: #dc2626; }
  .footer { text-align: center; color: #9ca3af; font-size: 0.75rem; margin-top: 24px; padding: 16px; }
  .authenticated-note { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: 0.875rem; color: #92400e; }
  .json-link { float: right; font-size: 0.75rem; text-decoration: none; color: #6366f1; }
</style>
</head>
<body>
<div class="container">
  <div style="display:flex;align-items:center;justify-content:space-between;">
    <div>
      <h1>&#128269; Laravel Health Check</h1>
      <p class="subtitle"><?= $results['timestamp'] ?> — <?= \htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') ?></p>
    </div>
    <a href="?key=<?= \htmlspecialchars($accessKey) ?>&json=1" class="json-link">View as JSON</a>
  </div>

  <div class="status-bar">
    <div class="status-badge status-<?= $results['status'] ?>">
      <?= $results['summary'] ?>
    </div>
  </div>

  <?php if (! $authenticated) { ?>
  <div class="authenticated-note">
    &#9888; Showing basic info only. Add <code>?key=health</code> to unlock full diagnostics (DB, .env, permissions, Laravel boot).
  </div>
  <?php } ?>

  <?php
  $groups = [];
foreach ($results['checks'] as $check) {
    $groups[$check['group']][] = $check;
}
?>

  <?php foreach ($groups as $groupName => $checks) { ?>
  <div class="group">
    <div class="group-header">
      <?= \htmlspecialchars(\ucfirst($groupName)) ?>
      <span style="font-weight:400;color:#6b7280;">
        (<?= \count(\array_filter($checks, fn ($c) => $c['passed'])) ?>/<?= \count($checks) ?>)
      </span>
    </div>
    <?php foreach ($checks as $check) { ?>
    <div class="check">
      <span class="icon"><?= $check['passed'] ? '&#10003;' : ($check['detail'] !== null ? '&#10007;' : '&mdash;') ?></span>
      <span class="label" style="color: <?= $check['passed'] ? 'inherit' : $red ?>;">
        <?= \htmlspecialchars($check['label']) ?>
        <?php if ($check['detail'] !== null) { ?>
        <span class="detail<?= $check['passed'] ? '' : ' fail-detail' ?>">— <?= \htmlspecialchars((string) $check['detail']) ?></span>
        <?php } ?>
      </span>
    </div>
    <?php } ?>
  </div>
  <?php } ?>

  <div class="footer">
    Reusable Laravel Health Check — drop in any project's <code>public/</code> directory
  </div>
</div>
</body>
</html>
