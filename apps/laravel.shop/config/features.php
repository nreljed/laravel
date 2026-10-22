<?php

use Nreljed\Blog\BlogServiceProvider;
use Nreljed\MarketingModule\MarketingServiceProvider;
use Nreljed\Platform\Ai\AiServiceProvider;
use Nreljed\Platform\PublicSite\PublicSiteServiceProvider;
use Nreljed\ShopModule\ShopServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Service Providers
    |--------------------------------------------------------------------------
    |
    | Self-contained features discovered by the shared FeatureRegistry.
    | Posts is provided by the blog package; Shop by the shop-module package.
    |
    */

    'providers' => [
        PublicSiteServiceProvider::class,
        BlogServiceProvider::class,
        MarketingServiceProvider::class,
        AiServiceProvider::class,
        ShopServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Defaults
    |--------------------------------------------------------------------------
    |
    | Per-feature enabled/disabled defaults. These sit below the persisted
    | settings table (FeatureSettingsService) but above the fail-closed
    | fallback in InteractsWithFeatures::defaultEnabled(). An admin can
    | toggle any feature at runtime via the modules settings page; this
    | config only controls the out-of-the-box state.
    |
    */

    'enabled' => [
        'public-site' => true,
        'posts' => true,
        'marketing' => true,
        'ai' => true,
        'shop' => true,
    ],

];
