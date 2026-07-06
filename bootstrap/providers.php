<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModuleServiceProvider;
use App\Providers\PermissionGateServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    ModuleServiceProvider::class,
    PermissionGateServiceProvider::class,
    TelescopeServiceProvider::class,
];
