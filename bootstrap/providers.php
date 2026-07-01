<?php

use App\Providers\AppServiceProvider;
use App\Providers\ModuleServiceProvider;
use App\Providers\PermissionGateServiceProvider;

return [
    AppServiceProvider::class,
    ModuleServiceProvider::class,
    PermissionGateServiceProvider::class,
];
