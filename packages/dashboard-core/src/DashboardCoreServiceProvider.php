<?php

namespace Nreljed\DashboardCore;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Nreljed\DashboardCore\Ai\Models\AiProvider;
use Nreljed\DashboardCore\Ai\Policies\AiProviderPolicy;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Models\Notification;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Notifications\ResetPassword as CustomResetPassword;
use Nreljed\DashboardCore\Notifications\VerifyEmail as CustomVerifyEmail;
use Nreljed\DashboardCore\Policies\PostPolicy;
use Nreljed\DashboardCore\Policies\RolePolicy;
use Nreljed\DashboardCore\Services\FeatureRegistry;
use Nreljed\DashboardCore\Services\FormattingService;
use Nreljed\DashboardCore\Services\MailerConfigurator;

class DashboardCoreServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/core.php', 'core');
        $this->mergeConfigFrom(__DIR__.'/../config/roles.php', 'roles');
        $this->mergeConfigFrom(__DIR__.'/../config/database-maintenance.php', 'database_maintenance');

        $this->app->singleton(FormattingService::class);
        $this->app->singleton(FeatureRegistry::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'core');

        if (config('core.load_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->loadRoutes();
        $this->loadConsoleRoutes();

        $this->configureRateLimiting();

        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(AiProvider::class, AiProviderPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }
        });

        $this->configureMailer();
        $this->configureNotifications();

        Notification::creating(function (Notification $notification) {
            $data = $notification->data;
            $notification->title ??= $data['title'] ?? $data['type'] ?? '';
            $notification->message ??= $data['message'] ?? $data['body'] ?? '';
            $notification->link ??= $data['link'] ?? $data['url'] ?? '';
            $notification->severity ??= $data['severity'] ?? 'info';
            $notification->module ??= $data['module'] ?? 'core';
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/core.php' => config_path('core.php'),
                __DIR__.'/../config/roles.php' => config_path('roles.php'),
                __DIR__.'/../config/database-maintenance.php' => config_path('database-maintenance.php'),
            ], 'core-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/core'),
            ], 'core-views');
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
            Console\Commands\CreateAdmin::class,
            Console\Commands\CreateRole::class,
            Console\Commands\ListPermissions::class,
            Console\Commands\PurgeNotifications::class,
            Console\Commands\PruneAuditLogs::class,
            Console\Commands\PublishScheduledContent::class,
        ]);
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

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
