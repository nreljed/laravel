<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Shop policy bindings moved to Nreljed\ShopModule\ShopServiceProvider::registerPolicies()

        // Share store contact details (from the project template-kit config)
        // so public pages render real contact info without hardcoding.
        Inertia::share('contact', function () {
            $contact = Config::get('contact', []);

            return [
                'email' => $contact['email'] ?? '',
                'phone' => $contact['phone'] ?? '',
                'address' => $contact['address'] ?? '',
                'hours' => $contact['hours'] ?? '',
            ];
        });
    }
}
