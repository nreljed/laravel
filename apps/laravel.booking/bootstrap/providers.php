<?php

use App\Providers\AppServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Nreljed\Blog\BlogServiceProvider;
use Nreljed\BookingModule\Booking\BookingServiceProvider;
use Nreljed\BookingModule\Properties\PropertiesServiceProvider;
use Nreljed\Platform\PermissionGateServiceProvider;
use Nreljed\Platform\PlatformServiceProvider;
use Nreljed\Platform\PublicSite\PublicSiteServiceProvider;

return array_filter([

    /*
     * Package Service Providers...
     */
    PlatformServiceProvider::class,
    BlogServiceProvider::class,
    PublicSiteServiceProvider::class,
    BookingServiceProvider::class,
    PropertiesServiceProvider::class,
    PermissionGateServiceProvider::class,

    /*
     * Application Service Providers...
     */
    AppServiceProvider::class,
    class_exists(TelescopeApplicationServiceProvider::class)
        ? TelescopeServiceProvider::class
        : null,

]);
