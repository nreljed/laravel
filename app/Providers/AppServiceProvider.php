<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Properties\Models\Property;
use App\Modules\Booking\Models\Booking;
use App\Policies\BookingPolicy;
use App\Policies\PropertyPolicy;
use App\Services\MailerConfigurator;
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
            if ($user->hasPermissionTo($ability)) {
                return true;
            }
        });

        Vite::prefetch(concurrency: 3);

        $this->configureMailer();
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
}
