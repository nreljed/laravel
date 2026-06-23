<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Properties\Models\Property;
use App\Modules\Booking\Models\Booking;
use App\Notifications\ResetPassword as CustomResetPassword;
use App\Notifications\VerifyEmail as CustomVerifyEmail;
use App\Policies\BookingPolicy;
use App\Policies\PropertyPolicy;
use App\Services\MailerConfigurator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);

        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }
        });

        Vite::prefetch(concurrency: 3);

        $this->configureMailer();
        $this->configureNotifications();
    }

    protected function configureMailer(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            return;
        }

        try {
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
