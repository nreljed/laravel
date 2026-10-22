<?php

namespace Nreljed\MarketingModule\Tests\Concerns;

use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\DateFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;

/**
 * A real (sqlite, in-memory) database for the publishing tests.
 *
 * Mirrors `scripts/migration-check.php`: the module is a library, so the tests
 * build the smallest container that can run Eloquent and the module's own
 * migrations instead of pulling in a full application harness.
 */
trait BootsDatabase
{
    protected Container $container;

    protected function bootDatabase(): void
    {
        $container = new Container;
        $container->instance('app', $container);
        $container->instance(Container::class, $container);

        $container->singleton(DispatcherContract::class, static fn () => new Dispatcher(new Container));
        $container->instance('events', $container->make(DispatcherContract::class));

        $container->singleton('config', static function (): ConfigRepository {
            $config = new ConfigRepository([]);

            $config->set('app.timezone', 'UTC');
            $config->set('database.default', 'sqlite');
            $config->set('database.connections.sqlite', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
            $config->set('features.providers', []);
            $config->set('marketing.publishing', [
                'max_attempts' => 3,
                'retry_backoff' => [60, 300, 900],
                'timezone' => 'UTC',
                'approval_required' => false,
                'max_targets' => 5,
            ]);
            $config->set('marketing.media', [
                'subdirectory' => 'social',
                'max_files' => 4,
                'max_file_size' => 10 * 1024 * 1024,
                'output_quality' => 85,
            ]);

            return $config;
        });

        $container->singleton('db.factory', static fn ($app) => new ConnectionFactory($app));

        $container->singleton('db', static fn ($app) => new DatabaseManager($app, $app['db.factory']));

        $container->singleton('db.schema', static fn ($app) => $app['db']->connection()->getSchemaBuilder());

        $container->alias('db', ConnectionResolverInterface::class);
        $container->alias('db', DatabaseManager::class);

        $container->instance('encrypter', new Encrypter(str_repeat('a', 32), 'AES-256-CBC'));
        $container->instance('date', new DateFactory);
        $container->instance(Generator::class, FakerFactory::create());

        // Facades cache the instance they resolved on first use, so a previous
        // test's connection would otherwise leak into this one (and every test
        // would share the first :memory: database).
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);

        Container::setInstance($container);

        Model::setConnectionResolver($container['db']);
        Model::setEventDispatcher($container['events']);

        $this->container = $container;

        $this->migrateModule();
    }

    /**
     * Provision the host's `users` table (the module's migrations reference it)
     * and run every module migration in filename order.
     */
    protected function migrateModule(): void
    {
        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        $migrations = glob(dirname(__DIR__, 2).'/database/migrations/*.php') ?: [];
        sort($migrations);

        foreach ($migrations as $file) {
            $migration = require $file;
            $migration->up();
        }
    }
}
