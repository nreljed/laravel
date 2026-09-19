<?php

use Nreljed\Platform\Ai\AiServiceProvider;
use Nreljed\Platform\PublicSite\PublicSiteServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Service Providers
    |--------------------------------------------------------------------------
    |
    | The self-contained feature providers discovered by the FeatureRegistry.
    | Each entry must be a registered service provider implementing the shared
    | feature contract (slug(), getNavItems(), getSharedData(), permissions(),
    | and the optional getDashboardData()/getAnalyticsSeries() hooks).
    |
    | Consuming apps publish this file (php artisan vendor:publish --tag
    | core-config) and add their own providers here — no core edits needed.
    |
    */

    'providers' => [
        PublicSiteServiceProvider::class,
        AiServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Enabled Defaults
    |--------------------------------------------------------------------------
    |
    | Per-feature on/off defaults, keyed by the provider slug. These sit below
    | the persisted settings table (FeatureSettingsService) but above the
    | fail-closed fallback in InteractsWithFeatures::defaultEnabled(). Admins
    | can toggle features at runtime via the modules settings page; this config
    | only controls the out-of-the-box state.
    |
    */

    'enabled' => [
        'public-site' => true,
        'ai' => true,
    ],

];
