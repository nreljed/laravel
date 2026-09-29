<?php

namespace Nreljed\ShopModule\Tests\Concerns;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Request as RequestMacro;
use Illuminate\Support\Facades\DB as DFacade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use Nreljed\ShopModule\Contracts\ProductSearchDriver;
use Nreljed\ShopModule\Search\DatabaseProductSearchDriver;
use Nreljed\ShopModule\Services\ProductSearch;
use Nreljed\ShopModule\Tests\Support\StubResponseFactory;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Boots the package's real migrations against a temporary SQLite file.
 *
 * The hand-rolled `class_exists` pipe tests could not catch a schema or
 * column mismatch, because they never opened a connection - they only proved
 * a class file parses. Schema built from the actual migration files keeps
 * these tests honest: a column a pipe writes but no migration creates fails
 * here immediately.
 *
 * A file database (not `:memory:`) is used so the schema is migrated once for
 * the whole process and each test only truncates. `:memory:` is scoped to the
 * connection, so it would force 69 migrations per test method.
 *
 * The container wiring mirrors `scripts/migration-check.php`, which is the
 * same shape CI uses to prove all 69 migrations apply.
 */
trait BootsShopSchema
{
    use ProvidesAnonymousAuth;

    private static ?string $databasePath = null;

    private static ?Container $shopContainer = null;

    protected function bootShopSchema(): void
    {
        if (self::$shopContainer !== null) {
            // The schema and its connection survive between tests; only the
            // global/facade pointers need restoring, because another test
            // class in the same process may have swapped them.
            Container::setInstance(self::$shopContainer);
            Facade::setFacadeApplication(self::$shopContainer);

            // Sibling test classes install their own dispatcher, and Eloquent
            // registers a model's boot hooks once per process (guarded by the
            // `$booted` cache). Without this, `Product` would stay bound to
            // whichever dispatcher happened to boot it first and its
            // `creating` hook - which stamps the NOT NULL `slug` - would
            // never fire here. Clearing the cache makes each model re-boot
            // against the dispatcher installed below.
            Model::setEventDispatcher(self::$shopContainer->make('events'));
            Model::clearBootedModels();

            return;
        }

        // `tempnam()` creates the bare name, so the `.sqlite` file the
        // connector checks for has to be materialised explicitly.
        //
        // The unlink has to cover the `.sqlite` path and not just the one
        // `tempnam()` returned. `tempnam()` guarantees that *its* name is
        // unused, but the `.sqlite` variant is a different file, and this
        // harness leaks one of those per test class per run. `touch()` does not
        // truncate, so a name that matches a leftover from an earlier run comes
        // back already migrated and the very next `Schema::create('users')`
        // dies with "table users already exists" - a failure that depends on
        // how many runs happened to be lying around, which is the worst kind to
        // meet in CI.
        $databasePath = tempnam(sys_get_temp_dir(), 'shop_schema_');
        unlink($databasePath);
        self::$databasePath = $databasePath.'.sqlite';

        if (file_exists(self::$databasePath)) {
            unlink(self::$databasePath);
        }

        touch(self::$databasePath);

        $basePath = dirname(__DIR__, 2);

        // The SQLite connector resolves relative paths through the
        // application base path, and `Illuminate\Foundation` helpers call
        // `app()->basePath()`. There is no Application instance here, so a
        // minimal container that answers those three accessors is enough.
        $container = new class($basePath) extends Container
        {
            public function __construct(private readonly string $base) {}

            public function basePath($path = ''): string
            {
                return $this->base.($path ? DIRECTORY_SEPARATOR.ltrim($path, '/\\') : '');
            }

            public function configPath($path = ''): string
            {
                return $this->basePath('config'.($path ? DIRECTORY_SEPARATOR.$path : ''));
            }

            public function databasePath($path = ''): string
            {
                return $this->basePath('database'.($path ? DIRECTORY_SEPARATOR.$path : ''));
            }

            /**
             * The global `abort()` helper delegates to the application, and
             * controllers use it (e.g. `CartController::findOwnCartItem()`
             * rejects a cart line the caller does not own with a 404). Without
             * this the rejection path throws "undefined method abort()" and a
             * test asserting the 404 can never observe it.
             */
            public function abort($code, $message = '', array $headers = []): void
            {
                throw $this->httpExceptionFor((int) $code, $message, $headers);
            }

            /**
             * @return array<int, class-string>
             */
            private function exceptionMap(): array
            {
                return [
                    400 => BadRequestHttpException::class,
                    401 => UnauthorizedHttpException::class,
                    403 => AccessDeniedHttpException::class,
                    404 => NotFoundHttpException::class,
                    405 => MethodNotAllowedHttpException::class,
                    409 => ConflictHttpException::class,
                    419 => HttpException::class,
                    422 => UnprocessableEntityHttpException::class,
                    429 => TooManyRequestsHttpException::class,
                    500 => HttpException::class,
                ];
            }

            private function httpExceptionFor(int $code, string $message, array $headers): HttpExceptionInterface
            {
                $class = $this->exceptionMap()[$code] ?? HttpException::class;

                // Most of these pin their own status, so `$code` is advisory,
                // and their signature is ($message, $previous, $code, $headers).
                //
                // `UnauthorizedHttpException` is the exception to that: it takes
                // a WWW-Authenticate `$challenge` first and the message second,
                // so the uniform call would pass null for a non-nullable string
                // and die with a TypeError instead of the 401 under test.
                if ($class === UnauthorizedHttpException::class) {
                    return new $class('', $message, null, $code, $headers);
                }

                // A controller that aborts without a message passes null, and
                // these constructors take a plain string.
                return new $class((string) $message, null, $code, $headers);
            }
        };

        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();

        $container->instance('path.base', $basePath);

        $container->instance('app', $container);
        $container->instance(Container::class, $container);

        $container->singleton(DispatcherContract::class, fn () => new Dispatcher(new Container));
        $container->instance('events', $container->make(DispatcherContract::class));

        // A `response()` helper for controllers under test. The harness has no
        // view stack or route table, so the real routing factory cannot be
        // built; this serves the plain-response methods and throws loudly for
        // the view/redirect ones rather than pretending to work.
        $container->singleton(ResponseFactory::class, fn () => new StubResponseFactory(
            fn (string $content, int $status, array $headers) => $content
        ));

        // Pipeline steps reject bad input with `ValidationException::withMessages()`,
        // which builds its error bag off the container's validator. A stub
        // factory is enough for that: no rules run, the messages are added
        // directly. Without it, executing such a step dies with
        // "Target class [validator] does not exist" before the step's own
        // assertion is ever reached.
        $container->singleton(ValidationFactory::class, fn () => new ValidationFactory(
            new Translator(new ArrayLoader, 'en'),
        ));
        $container->alias(ValidationFactory::class, 'validator');

        $this->bindAnonymousAuth($container);

        // Mirrors the provider's own registration: controllers resolve the
        // search service through the container, so the harness stands in for
        // the `ProductSearch`/`ProductSearchDriver` singletons exactly the way
        // it does for the response factory and the validator stub.
        $container->singleton(ProductSearchDriver::class, fn ($app) => $app->make(
            $app['config']->get('shop.search.driver', DatabaseProductSearchDriver::class)
        ));
        $container->singleton(ProductSearch::class, fn ($app) => new ProductSearch(
            $app->make(ProductSearchDriver::class)
        ));

        $connection = [
            'driver' => 'sqlite',
            'database' => self::$databasePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        $container->singleton('config', fn () => new ConfigRepository([
            'database' => [
                'default' => 'sqlite',
                'connections' => ['sqlite' => $connection],
            ],
            // `CreateOrderItems` reads the cart-line pipe list from config,
            // so the package config has to be present.
            'shop' => require $basePath.'/config/shop.php',
        ]));
        $container->alias('config', ConfigContract::class);

        // Several services read through the `Cache` facade
        // (`CurrencyService::getDefaultCurrency()` memoises the active
        // currencies for an hour, for one). An array-backed repository keeps
        // those paths executable without a filesystem cache, and keeps the
        // memoisation from leaking between tests.
        $container->singleton('cache', fn () => new Repository(new ArrayStore));

        $container->singleton('db.factory', fn ($app) => new ConnectionFactory($app));
        $container->singleton('db', fn ($app) => new DatabaseManager($app, $app['db.factory']));
        $container->singleton('db.schema', fn ($app) => $app['db']->connection()->getSchemaBuilder());
        $container->alias('db', ConnectionResolverInterface::class);
        $container->alias('db', DatabaseManager::class);

        $capsule = new Capsule($container);
        $capsule->addConnection($connection);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // Route model events at the container's dispatcher so the models'
        // own boot hooks still run - `Order::creating()` generates
        // `order_number` and `Product::creating()` generates `slug`, and
        // without them every insert violates a NOT NULL constraint. No
        // feature provider is booted here, so nothing else subscribes.
        Model::setEventDispatcher($container->make('events'));
        Model::clearBootedModels();

        // Several migrations reference the facades as global `DB` / `Schema`
        // rather than importing them. Same aliases CI's migration gate uses.
        foreach (['DB' => DFacade::class, 'Schema' => Schema::class] as $short => $fqcn) {
            if (! class_exists($short, false)) {
                class_alias($fqcn, $short);
            }
        }

        $this->runMigrations();

        $this->registerRequestSignatureMacros();

        self::$shopContainer = $container;

        $this->pruneOrphanedSchemaFiles();
    }

    /**
     * Reclaim the temp databases earlier runs leaked.
     *
     * Deleting this run's own file at shutdown does not work on Windows: the
     * Eloquent capsule holds its own reference to the same PDO, so the handle is
     * still open when the shutdown handler runs and the unlink is refused
     * silently. Pruning by age sidesteps the locking question entirely - a file
     * older than an hour belongs to a process that finished long ago, and the
     * threshold is what keeps a second suite running concurrently on this
     * machine from having its live schema deleted underneath it.
     *
     * Worth doing on the self-hosted Windows runners, which are long-lived:
     * these files are ~487 KB each and without this they accumulate for as long
     * as the runner does.
     */
    private function pruneOrphanedSchemaFiles(): void
    {
        $current = self::$databasePath;
        $cutoff = time() - 3600;

        foreach (glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'sho*.tmp.sqlite') ?: [] as $path) {
            if ($path === $current || ! is_file($path)) {
                continue;
            }

            $modified = @filemtime($path);

            if ($modified === false || $modified >= $cutoff) {
                continue;
            }

            @unlink($path);
        }
    }

    /**
     * `Illuminate\Foundation\Providers\FoundationServiceProvider` registers
     * these as request macros, and every controller that guards a link with
     * `$request->hasValidSignature()` needs them. The harness has no
     * Foundation provider, so without this the call dies with
     * "Method hasValidSignature does not exist" - which reads as a broken
     * controller rather than a missing provider.
     *
     * Mirrors the framework's own registration: URL facade -> `url` binding.
     */
    private function registerRequestSignatureMacros(): void
    {
        if (! Request::hasMacro('hasValidSignature')) {
            RequestMacro::macro('hasValidSignature', function ($absolute = true) {
                return Container::getInstance()->make('url')->hasValidSignature($this, $absolute);
            });

            RequestMacro::macro('hasValidSignatureWhileIgnoring', function ($ignoreQuery = [], $absolute = true) {
                return Container::getInstance()->make('url')->hasValidSignature($this, $absolute, $ignoreQuery);
            });
        }
    }

    private function runMigrations(): void
    {
        // Several package migrations reference the host app's `users` table
        // (user_id / created_by / processed_by foreign keys). The consuming
        // app owns it, so provision a minimal stub - same as the CI gate.
        Schema::create('users', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $files = glob(dirname(__DIR__, 2).'/database/migrations/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            (require $file)->up();
        }
    }

    /**
     * A `assertDatabaseHas` for plain PHPUnit.
     *
     * These tests extend `PHPUnit\Framework\TestCase` rather than Laravel's
     * `TestCase`, so the usual database assertions are unavailable.
     */
    protected function assertRowExists(string $table, array $where, string $message = ''): void
    {
        $actual = Schema::getConnection()->table($table)->where($where)->first();

        $this->assertNotNull($actual, $message !== '' ? $message : "Expected a row in [{$table}] matching ".json_encode($where));
    }

    protected function assertRowMissing(string $table, array $where, string $message = ''): void
    {
        $actual = Schema::getConnection()->table($table)->where($where)->first();

        $this->assertNull($actual, $message !== '' ? $message : "Expected no row in [{$table}] matching ".json_encode($where));
    }

    /**
     * Empty every table so each test starts from a known state.
     *
     * Foreign keys are disabled for these runs, so truncation order is
     * irrelevant.
     */
    protected function resetShopTables(): void
    {
        $connection = Schema::getConnection();

        foreach ($connection->getSchemaBuilder()->getTableListing() as $table) {
            if ($table !== 'sqlite_sequence') {
                $connection->table($table)->delete();
            }
        }
    }
}
