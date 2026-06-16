<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
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

Route::post('/deploy/update-env', function () {
    $token = request('token');
    if ($token !== env('DEPLOY_TOKEN')) {
        abort(403);
    }
    
    $envContent = request('content');
    $envPath = base_path('.env');
    
    if (File::isWritable($envPath)) {
        File::put($envPath, $envContent);
        return 'Environment file updated successfully';
    }
    
    return 'Unable to write .env file';
});
