<?php

declare(strict_types=1);

namespace Nreljed\Platform\PublicSite;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\FeatureProvider;

/**
 * PublicSite feature module — public marketing pages (/, about, services,
 * contact) plus maintenance-mode gates. Template *page components* ship in
 * the starter theme (`resources/themes/starter`) or the project; this module
 * only owns the routes and controller.
 */
class PublicSiteServiceProvider extends ServiceProvider implements FeatureProvider
{
    use InteractsWithFeatures;

    public function name(): string
    {
        return 'Public Site';
    }

    public function slug(): string
    {
        return 'public-site';
    }

    public function description(): ?string
    {
        return 'Public marketing pages behind maintenance-mode checks.';
    }

    public function boot(): void
    {
        Route::middleware(['web', 'maintenance'])
            ->group(__DIR__.'/Routes/web.php');
    }
}