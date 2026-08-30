<?php

use App\TestPosts\Models\TestPost;
use App\TestPosts\TestPostsServiceProvider;
use Nreljed\DashboardCore\Ai\AiServiceProvider;
use Nreljed\DashboardCore\Blog\BlogServiceProvider;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\PublicSite\PublicSiteServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Load Migrations
    |--------------------------------------------------------------------------
    |
    | Disable package migrations — this product already has its own migration
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
        BlogServiceProvider::class,
        AiServiceProvider::class,
        TestPostsServiceProvider::class,
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
