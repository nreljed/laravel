<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap Admin
    |--------------------------------------------------------------------------
    |
    | Credentials used by app:create-admin and the deploy pipeline when no
    | explicit credentials are provided. Values come from environment.
    |
    */

    'email' => env('ADMIN_EMAIL'),

    'password' => env('ADMIN_PASSWORD'),

    'name' => env('ADMIN_NAME', 'Admin'),

    'username' => env('ADMIN_USERNAME'),

];
