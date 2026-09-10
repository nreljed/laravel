<?php

use App\Providers\AppServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\TestPosts\TestPostsServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Nreljed\DashboardCore\Ai\AiServiceProvider;
use Nreljed\DashboardCore\DashboardCoreServiceProvider;
use Nreljed\DashboardCore\Posts\PostsServiceProvider;
use Nreljed\DashboardCore\Providers\PermissionGateServiceProvider;
use Nreljed\DashboardCore\PublicSite\PublicSiteServiceProvider;

return array_filter([
    DashboardCoreServiceProvider::class,
    PostsServiceProvider::class,
    PublicSiteServiceProvider::class,
    AiServiceProvider::class,
    PermissionGateServiceProvider::class,
    AppServiceProvider::class,
    TestPostsServiceProvider::class,
    class_exists(TelescopeApplicationServiceProvider::class)
        ? TelescopeServiceProvider::class
        : null,
]);
