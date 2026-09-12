<?php

use App\Providers\AppServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\TestPosts\TestPostsServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Nreljed\Blog\BlogServiceProvider;
use Nreljed\Platform\Ai\AiServiceProvider;
use Nreljed\Platform\PlatformServiceProvider;
use Nreljed\Platform\Providers\PermissionGateServiceProvider;
use Nreljed\Platform\PublicSite\PublicSiteServiceProvider;

return array_filter([
    PlatformServiceProvider::class,
    BlogServiceProvider::class,
    PublicSiteServiceProvider::class,
    AiServiceProvider::class,
    PermissionGateServiceProvider::class,
    AppServiceProvider::class,
    TestPostsServiceProvider::class,
    class_exists(TelescopeApplicationServiceProvider::class)
        ? TelescopeServiceProvider::class
        : null,
]);
