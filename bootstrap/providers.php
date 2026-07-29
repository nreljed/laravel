<?php

use App\Ai\AiServiceProvider;
use App\Blog\BlogServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\PermissionGateServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\TestPosts\TestPostsServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

return array_filter([
    AppServiceProvider::class,
    AiServiceProvider::class,
    BlogServiceProvider::class,
    TestPostsServiceProvider::class,
    PermissionGateServiceProvider::class,
    class_exists(TelescopeApplicationServiceProvider::class)
        ? TelescopeServiceProvider::class
        : null,
]);
