<?php

use Illuminate\Support\Facades\Route;
use Nreljed\Platform\Deploy\Controllers\DeployController;

/*
|--------------------------------------------------------------------------
| DEPLOY Endpoints — token-protected, no CSRF, rate-limited
|--------------------------------------------------------------------------
|
| Check + cache-clear accept GET so cron/probe workflows can poll status.
| Loaded from the kernel provider's routes/web.php (the package's own
| infrastructure block — NOT a FeatureProvider).
|
*/

Route::post('/deploy/upload', [DeployController::class, 'upload'])->middleware('throttle:5,1');
Route::match(['get', 'post'], '/deploy/cache-clear', [DeployController::class, 'cacheClear'])->middleware('throttle:5,1');
Route::match(['get', 'post'], '/deploy/check', [DeployController::class, 'check'])->middleware('throttle:5,1');
Route::post('/deploy/migrate', [DeployController::class, 'migrate'])->middleware('throttle:5,1');
Route::post('/deploy/create-admin', [DeployController::class, 'createAdmin'])->middleware('throttle:5,1');

Route::post('/deploy/env', [DeployController::class, 'updateEnvValues'])->middleware('throttle:5,1');
Route::post('/deploy/reset', [DeployController::class, 'resetDatabase'])->middleware('throttle:5,1');
