<?php

namespace App\Providers;

use App\Blog\Models\Post;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\ResetPassword as CustomResetPassword;
use App\Notifications\VerifyEmail as CustomVerifyEmail;
use App\Policies\PostPolicy;
use App\Services\FeatureRegistry;
use App\Services\FormattingService;
use App\Services\MailerConfigurator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FormattingService::class);
        $this->app->singleton(FeatureRegistry::class);
    }

    public function boot(): void
    {
        Gate::policy(Post::class, PostPolicy::class);

        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }
        });

        Vite::prefetch(concurrency: 3);

        $this->configureMailer();
        $this->configureNotifications();

        Notification::creating(function (Notification $notification) {
            $data = $notification->data;
            if (is_array($data)) {
                $notification->title ??= $data['title'] ?? $data['type'] ?? '';
                $notification->message ??= $data['message'] ?? $data['body'] ?? '';
                $notification->link ??= $data['link'] ?? $data['url'] ?? '';
                $notification->severity ??= $data['severity'] ?? 'info';
                $notification->module ??= $data['module'] ?? 'core';
            }
        });
    }

    protected function configureMailer(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $configurator = $this->app->make(MailerConfigurator::class);
            $configurator->configure();
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
}
