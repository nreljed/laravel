<?php

namespace Nreljed\ShopModule\Tests\Concerns;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactoryContract;
use Illuminate\Contracts\Auth\Guard as GuardContract;
use Illuminate\Contracts\Config\Repository as ConfigContract;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request as RequestMacro;
use Illuminate\Support\Facades\DB as DFacade;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Routing\ResponseFactory as ResponseFactory;
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
        $databasePath = tempnam(sys_get_temp_dir(), 'shop_schema_');
        unlink($databasePath);
        self::$databasePath = $databasePath.'.sqlite';
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

        $this->bindAnonymousAuth($container);

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
        if (! \Illuminate\Http\Request::hasMacro('hasValidSignature')) {
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
