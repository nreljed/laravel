<?php

use Nreljed\Blog\BlogServiceProvider;
use Nreljed\MarketingModule\MarketingServiceProvider;
use Nreljed\Platform\Ai\AiServiceProvider;
use Nreljed\Platform\PublicSite\PublicSiteServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Service Providers
    |--------------------------------------------------------------------------
    |
    | Self-contained features discovered by the shared FeatureRegistry. Each
    | entry must be a registered service provider implementing the feature
    | contract (slug(), getNavItems(), getSharedData(), permissions()).
    |
    */

    'providers' => [
        PublicSiteServiceProvider::class,
        BlogServiceProvider::class,
        AiServiceProvider::class,
        MarketingServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Defaults
    |--------------------------------------------------------------------------
    |
    | Per-feature enabled/disabled defaults. These sit below the persisted
    | settings table (FeatureSettingsService) but above the fail-closed
    | fallback in InteractsWithFeatures::defaultEnabled().
    |
    */

    'enabled' => [
        'public-site' => true,
        'posts' => true,
        'ai' => true,
        'marketing' => true,
    ],

];
