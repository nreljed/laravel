<?php

use Nreljed\Blog\BlogServiceProvider;
use Nreljed\BookingModule\Booking\BookingServiceProvider;
use Nreljed\BookingModule\Properties\PropertiesServiceProvider;
use Nreljed\Platform\PublicSite\PublicSiteServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Service Providers
    |--------------------------------------------------------------------------
    |
    | Self-contained features discovered by the shared FeatureRegistry.
    | Posts is provided by the blog package; Bookings and Properties by the
    | booking-module package.
    |
    */

    'providers' => [
        PublicSiteServiceProvider::class,
        BlogServiceProvider::class,
        BookingServiceProvider::class,
        PropertiesServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Defaults
    |--------------------------------------------------------------------------
    |
    | Per-feature enabled/disabled defaults. These sit below the persisted
    | settings table (FeatureSettingsService) but above the fail-closed
    | fallback in InteractsWithFeatures::defaultEnabled(). An admin can
    | toggle any feature at runtime via the modules settings page; this
    | config only controls the out-of-the-box state.
    |
    */

    'enabled' => [
        'public-site' => true,
        'posts' => true,
        'bookings' => true,
        'properties' => true,
    ],

];
