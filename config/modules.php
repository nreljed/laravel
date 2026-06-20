<?php

return [
    'paths' => [
        app_path('Modules'),
    ],

    'defaults' => [
        'route_prefix' => '/dashboard',
        'controller_namespace' => 'Http\\Controllers',
        'model_namespace' => 'Models',
    ],

    'dev' => [
        'auto_register_all' => env('MODULES_AUTO_REGISTER_ALL', false),
    ],
];
