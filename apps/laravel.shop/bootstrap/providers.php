<?php

use App\Providers\AppServiceProvider;
use App\Providers\FrontendRouteServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Nreljed\Blog\BlogServiceProvider;
use Nreljed\MarketingModule\MarketingServiceProvider;
use Nreljed\Platform\Ai\AiServiceProvider;
use Nreljed\Platform\PlatformServiceProvider;
use Nreljed\Platform\Providers\PermissionGateServiceProvider;
use Nreljed\Platform\PublicSite\PublicSiteServiceProvider;
use Nreljed\ShopModule\ShopServiceProvider;

return array_filter([
    PlatformServiceProvider::class,
    FrontendRouteServiceProvider::class,
    AppServiceProvider::class,
    ShopServiceProvider::class,
    BlogServiceProvider::class,
    MarketingServiceProvider::class,
    PublicSiteServiceProvider::class,
    AiServiceProvider::class,
    PermissionGateServiceProvider::class,
    class_exists(TelescopeApplicationServiceProvider::class)
        ? TelescopeServiceProvider::class
        : null,
]);
