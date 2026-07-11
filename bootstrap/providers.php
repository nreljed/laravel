<?php

use App\Blog\BlogServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\PermissionGateServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

return array_filter([
    AppServiceProvider::class,
    BlogServiceProvider::class,
    PermissionGateServiceProvider::class,
    class_exists(TelescopeApplicationServiceProvider::class)
        ? TelescopeServiceProvider::class
        : null,
]);
