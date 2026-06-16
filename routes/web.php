<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/deploy/cache-clear', function () {
    $token = request('token');
    if ($token !== env('DEPLOY_TOKEN')) {
        abort(403);
    }
    
    Artisan::call('config:cache');
    Artisan::call('route:cache');
    Artisan::call('view:cache');
    Artisan::call('migrate', ['--force' => true]);
    
    return 'Cache cleared and migrations run successfully';
});
