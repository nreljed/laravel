<?php

namespace Nreljed\Platform;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Nreljed\Platform\Deploy\Middleware\VerifyDeployToken;
use Nreljed\Platform\Http\Middleware\AdminMiddleware;
use Nreljed\Platform\Http\Middleware\CheckIpAccess;
use Nreljed\Platform\Http\Middleware\CheckMaintenanceMode;
use Nreljed\Platform\Http\Middleware\CheckPermission;
use Nreljed\Platform\Http\Middleware\CheckRole;
use Nreljed\Platform\Http\Middleware\ConfigureSessionLifetime;
use Nreljed\Platform\Http\Middleware\ContentSecurityPolicy;
use Nreljed\Platform\Http\Middleware\HandleInertiaRequests;
use Nreljed\Platform\Models\Notification;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Notifications\ResetPassword as CustomResetPassword;
use Nreljed\Platform\Notifications\VerifyEmail as CustomVerifyEmail;
use Nreljed\Platform\Observers\UserObserver;
use Nreljed\Platform\Policies\RolePolicy;
use Nreljed\Platform\Services\ContentRegistry;
use Nreljed\Platform\Services\CoreVersionService;
use Nreljed\Platform\Services\FeatureRegistry;
use Nreljed\Platform\Services\FormattingService;
use Nreljed\Platform\Services\MailerConfigurator;
use Nreljed\Platform\Services\NotificationService;

class PlatformServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/core.php', 'core');
        $this->mergeConfigFrom(__DIR__.'/../config/roles.php', 'roles');
        $this->mergeConfigFrom(__DIR__.'/../config/database-maintenance.php', 'database_maintenance');
        $this->mergeConfigFrom(__DIR__.'/../config/database-backup.php', 'database_backup');
        $this->mergeConfigFrom(__DIR__.'/../config/admin.php', 'admin');
        $this->mergeConfigFrom(__DIR__.'/../config/deploy.php', 'deploy');
        $this->mergeConfigFrom(__DIR__.'/../config/features.php', 'features');
        $this->mergeConfigFrom(__DIR__.'/../config/media.php', 'media');

        $this->app->singleton(FormattingService::class);
        $this->app->singleton(FeatureRegistry::class);
        $this->app->singleton(ContentRegistry::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'core');

        if (config('core.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->loadRoutes();
        $this->loadConsoleRoutes();
        $this->configureSchedules();

        $this->configureMiddleware();
        $this->configureRateLimiting();

        RedirectIfAuthenticated::redirectUsing(fn () => route('dashboard.index'));

        Gate::policy(Role::class, RolePolicy::class);

        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }
        });

        User::observe(UserObserver::class);

        $this->configureMailer();
        $this->configureNotifications();
        $this->configureErrorReporting();

        Notification::creating(function (Notification $notification) {
            $data = $notification->data;
            $notification->title ??= $data['title'] ?? $data['type'] ?? '';
            $notification->message ??= $data['message'] ?? $data['body'] ?? '';
            $notification->link ??= $data['link'] ?? $data['url'] ?? '';
            $notification->severity ??= $data['severity'] ?? 'info';
            $notification->module ??= $data['module'] ?? 'core';

            if ($notification->notifiable_id) {
                app(NotificationService::class)->invalidateUnreadCache($notification->notifiable_id);
            }
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/core.php' => config_path('core.php'),
                __DIR__.'/../config/roles.php' => config_path('roles.php'),
                __DIR__.'/../config/database-maintenance.php' => config_path('database-maintenance.php'),
                __DIR__.'/../config/database-backup.php' => config_path('database-backup.php'),
                __DIR__.'/../config/admin.php' => config_path('admin.php'),
                __DIR__.'/../config/deploy.php' => config_path('deploy.php'),
                __DIR__.'/../config/features.php' => config_path('features.php'),
                __DIR__.'/../config/media.php' => config_path('media.php'),
            ], 'core-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/core'),
            ], 'core-views');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'core-migrations');
        }
    }

    protected function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../routes/web.php');
    }

    protected function loadConsoleRoutes(): void
    {
        $this->commands([
            Console\Commands\CleanupOrphanMedia::class,
            Console\Commands\CoreVersion::class,
            Console\Commands\CreateAdmin::class,
            Console\Commands\CreateRole::class,
            Console\Commands\DatabaseBackup::class,
            Console\Commands\DeployAuto::class,
            Console\Commands\ListPermissions::class,
            Console\Commands\MakeFeature::class,
            Console\Commands\MakeResource::class,
            Console\Commands\PlatformInstall::class,
            Console\Commands\PurgeNotifications::class,
            Console\Commands\PruneAuditLogs::class,
        ]);
    }

    protected function configureSchedules(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            if (config('deploy.auto_enabled', true)) {
                $schedule->command('deploy:auto')
                    ->everyFiveMinutes()
                    ->withoutOverlapping();
            }

            if (config('database_backup.enabled', true)) {
                $schedule->command('database:backup')
                    ->dailyAt(config('database_backup.schedule', '03:17'))
                    ->withoutOverlapping();
            }
        });
    }

    protected function configureMailer(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $this->app->make(MailerConfigurator::class)->configure();
        } catch (\Throwable $e) {
            report($e);
        }

        Event::listen(MessageSending::class, function (MessageSending $event): void {
            try {
                $this->app->make(MailerConfigurator::class)->applyMessageSettings($event->message);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    protected function configureNotifications(): void
    {
        VerifyEmail::toMailUsing(function ($notifiable, $verificationUrl) {
            $notification = new CustomVerifyEmail($verificationUrl);

            return $notification->toMail($notifiable);
        });

        ResetPassword::toMailUsing(function ($notifiable, $token) {
            $notification = new CustomResetPassword($token);

            return $notification->toMail($notifiable);
        });
    }

    /**
     * Optional Sentry error/performance monitoring, gated so the app boots
     * perfectly fine without the package or a DSN.
     *
     * Wiring is a no-op unless BOTH conditions hold: the
     * sentry/sentry-laravel package is installed (class_exists gate — CI/offline
     * installs without it are unaffected) AND a DSN is configured via
     * SENTRY_LARAVEL_DSN (or legacy SENTRY_DSN). When active, the release is
     * pinned to the app/core version so errors group per deploy.
     */
    protected function configureErrorReporting(): void
    {
        if (! class_exists(\Sentry\Laravel\ServiceProvider::class)) {
            return;
        }

        $dsn = (string) config('sentry.dsn', env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')));

        if ($dsn === '') {
            return;
        }

        $release = (string) env('SENTRY_RELEASE');
        if ($release === '') {
            $release = (string) $this->app->make(CoreVersionService::class)->info()['version'];
        }

        Config::set('sentry.release', $release !== '' ? $release : null);

        // Performance tracing is opt-in: SENTRY_TRACES_SAMPLE_RATE (0.0–1.0)
        // is the sample rate, defaulting to disabled (0.0) so a bare DSN never
        // increases request volume/cost without an explicit choice.
        $tracesRate = filter_var(env('SENTRY_TRACES_SAMPLE_RATE', 0.0), FILTER_VALIDATE_FLOAT);

        Config::set('sentry.traces_sample_rate', $tracesRate === false ? 0.0 : max(0.0, min(1.0, $tracesRate)));
    }

    protected function configureMiddleware(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('admin', AdminMiddleware::class);
        $router->aliasMiddleware('role', CheckRole::class);
        $router->aliasMiddleware('permission', CheckPermission::class);
        $router->aliasMiddleware('maintenance', CheckMaintenanceMode::class);
        $router->aliasMiddleware('session-lifetime', ConfigureSessionLifetime::class);
        $router->aliasMiddleware('ip-access', CheckIpAccess::class);
        $router->aliasMiddleware('deploy-token', VerifyDeployToken::class);

        $webMiddleware = [
            ConfigureSessionLifetime::class,
            CheckIpAccess::class,
            ContentSecurityPolicy::class,
            config('core.inertia_middleware', HandleInertiaRequests::class),
            AddLinkHeadersForPreloadedAssets::class,
        ];

        $kernel = $this->app->make(HttpKernelContract::class);

        foreach ($webMiddleware as $middleware) {
            $kernel->appendMiddlewareToGroup('web', $middleware);
        }

        PreventRequestForgery::except([
            'dashboard/account/avatar',
            'deploy/*',
            'csp-report',
        ]);
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
