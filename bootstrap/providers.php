<?php

use App\Providers\AppServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Nreljed\Blog\BlogServiceProvider;
use Nreljed\MarketingModule\MarketingServiceProvider;
use Nreljed\Platform\Ai\AiServiceProvider;
use Nreljed\Platform\PlatformServiceProvider;
use Nreljed\Platform\Providers\PermissionGateServiceProvider;
use Nreljed\Platform\PublicSite\PublicSiteServiceProvider;

return array_filter([
    PlatformServiceProvider::class,
    BlogServiceProvider::class,
    PublicSiteServiceProvider::class,
    AiServiceProvider::class,
    MarketingServiceProvider::class,
    PermissionGateServiceProvider::class,
    AppServiceProvider::class,
    class_exists(TelescopeApplicationServiceProvider::class)
        ? TelescopeServiceProvider::class
        : null,
]);
