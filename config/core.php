<?php

use App\TestPosts\Models\TestPost;
use App\TestPosts\TestPostsServiceProvider;
use Nreljed\DashboardCore\Ai\AiServiceProvider;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Posts\Models\Post;
use Nreljed\DashboardCore\Posts\PostsServiceProvider;
use Nreljed\DashboardCore\PublicSite\PublicSiteServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Load Migrations
    |--------------------------------------------------------------------------
    |
    | Disable package migrations - this product already has its own migration
    | files for the same tables.
    |
    */

    'load_migrations' => false,

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

    'features' => [
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

    'features_enabled' => [
        'public-site' => true,
        'posts' => true,
        'ai' => true,
        'test-posts' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Orphan Media Targets
    |--------------------------------------------------------------------------
    |
    | Folder => model class pairs scanned by the core:cleanup-orphan-media
    | command. Each model class must expose a getReferencedPaths() method that
    | returns the storage paths it references.
    |
    */

    'orphan_media' => [
        'posts' => Post::class,
        'test-posts' => TestPost::class,
        'avatars' => User::class,
    ],

];
