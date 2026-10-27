<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nreljed\ShopModule\Http\Controllers\StorefrontController;

/**
 * Registers project-level public routes that must take precedence over the
 * nreljed/platform package defaults.
 *
 * platform registers its own `/` route (named `home`) while the app
 * boots. Laravel's RouteCollection keeps only the LAST route registered for a
 * given URI + method pair, so registering here in `register()` is not enough —
 * the package route would overwrite ours. Instead we register the route inside
 * a `booted()` callback, which runs after every provider has booted, so our
 * route always wins.
 */
class FrontendRouteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->booted(function () {
            // `maintenance` is carried by every other public page (platform
            // owns about/services/contact, the shop and blog packages own
            // their prefixes). `/` renders the storefront, so it needs the
            // same gate - otherwise the home page keeps serving while the
            // rest of the site answers 503, and during a `migrate:fresh` run
            // that means rendering from tables that no longer exist.
            Route::middleware(['web', 'maintenance'])
                ->get('/', [StorefrontController::class, 'index'])
                ->name('home');
        });
    }
}
