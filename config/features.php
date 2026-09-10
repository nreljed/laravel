<?php

use App\TestPosts\TestPostsServiceProvider;
use Nreljed\DashboardCore\Ai\AiServiceProvider;
use Nreljed\DashboardCore\Posts\PostsServiceProvider;
use Nreljed\DashboardCore\PublicSite\PublicSiteServiceProvider;

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
        PostsServiceProvider::class,
        AiServiceProvider::class,
        TestPostsServiceProvider::class,
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
        'test-posts' => true,
    ],

];
