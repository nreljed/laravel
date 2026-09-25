<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__);

$candidates = [
    $root.'/vendor/autoload.php',
    $root.'/../../../../vendor/autoload.php',
    $root.'/../../../vendor/autoload.php',
];

foreach ($candidates as $autoload) {
    if (is_file($autoload)) {
        require $autoload;

        break;
    }
}

$container = new Container;
$container->instance('app', $container);
$container->instance(Container::class, $container);

$container->singleton(DispatcherContract::class, function () {
    return new Dispatcher(new Container);
});
$container->instance('events', $container->make(DispatcherContract::class));

$container->singleton('config', function () {
    $config = new Illuminate\Config\Repository([]);

    // Default to in-memory sqlite. Set MIGRATION_CHECK_DB_HOST to point the
    // same migrations at another driver instead — this check exists to catch
    // schema that only one engine accepts, and running it on sqlite alone let a
    // STORED-generated-column + foreign key combination ship that MySQL rejects
    // (error 138), which broke a production deploy.
    $host = getenv('MIGRATION_CHECK_DB_HOST') ?: null;

    if ($host) {
        $config->set('database.default', 'mysql');
        $config->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => $host,
            'port' => getenv('MIGRATION_CHECK_DB_PORT') ?: '3306',
            'database' => getenv('MIGRATION_CHECK_DB_DATABASE') ?: 'forge',
            'username' => getenv('MIGRATION_CHECK_DB_USERNAME') ?: 'root',
            'password' => getenv('MIGRATION_CHECK_DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
            'foreign_key_constraints' => true,
        ]);

        return $config;
    }

    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    return $config;
});

$container->singleton('db.factory', function ($app) {
    return new ConnectionFactory($app);
});

$container->singleton('db', function ($app) {
    return new DatabaseManager($app, $app['db.factory']);
});

$container->singleton('db.schema', function ($app) {
    return $app['db']->connection()->getSchemaBuilder();
});

$container->alias('db', ConnectionResolverInterface::class);
$container->alias('db', DatabaseManager::class);

Facade::setFacadeApplication($container);

$aliases = [
    'DB' => DB::class,
    'Schema' => Schema::class,
];
foreach ($aliases as $short => $fqcn) {
    if (! class_exists($short, false)) {
        class_alias($fqcn, $short);
    }
}

// The package migrations reference the host's `users` table (created_by /
// processed_by / approved_by / user_id FKs), which lives in the consuming app.
// Provision a minimal stub so the schema is fully realisable on sqlite.
Schema::create('users', function ($table) {
    $table->id();
    $table->timestamps();
});

$migrations = glob($root.'/database/migrations/*.php') ?: [];
sort($migrations);

$failures = 0;
foreach ($migrations as $file) {
    $name = basename($file);
    try {
        $migration = require $file;
        $migration->up();
        echo '  OK  '.$name.PHP_EOL;
    } catch (Throwable $e) {
        $failures++;
        echo '  FAIL '.$name.PHP_EOL;
        echo '       '.$e->getMessage().PHP_EOL;
    }
}

DB::disconnect();

$driver = getenv('MIGRATION_CHECK_DB_HOST') ? 'mysql' : 'sqlite';

if ($failures > 0) {
    fwrite(STDERR, "MIGRATION CHECK FAILED: {$failures} migration(s) cannot run against {$driver}.\n");

    exit(1);
}

echo 'MIGRATION CHECK OK: '.count($migrations)." migrations run cleanly against {$driver}."."\n";

exit(0);