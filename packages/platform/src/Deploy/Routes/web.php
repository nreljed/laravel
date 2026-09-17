<?php

use Illuminate\Support\Facades\Route;
use Nreljed\Platform\Deploy\Controllers\DeployController;

/*
|--------------------------------------------------------------------------
| DEPLOY Endpoints — token-protected (deploy-token middleware), no CSRF,
| rate-limited
|--------------------------------------------------------------------------
|
| Check + cache-clear accept GET so cron/probe workflows can poll status.
| Loaded from the kernel provider's routes/web.php (the package's own
| infrastructure block — NOT a FeatureProvider).
|
| Token auth is enforced centrally by the `deploy-token` middleware on the
| whole group so future endpoints cannot silently skip it. The token is read
| from the `token` request input (query or JSON body) or an `Authorization:
| Bearer` header, compared constant-time against config('deploy.token').
|
*/

Route::middleware('deploy-token')->group(function () {
    Route::post('/deploy/upload', [DeployController::class, 'upload'])->middleware('throttle:5,1');
    Route::match(['get', 'post'], '/deploy/cache-clear', [DeployController::class, 'cacheClear'])->middleware('throttle:5,1');
    Route::match(['get', 'post'], '/deploy/check', [DeployController::class, 'check'])->middleware('throttle:5,1');
    Route::post('/deploy/migrate', [DeployController::class, 'migrate'])->middleware('throttle:5,1');
    Route::post('/deploy/create-admin', [DeployController::class, 'createAdmin'])->middleware('throttle:5,1');
    Route::post('/deploy/env', [DeployController::class, 'updateEnvValues'])->middleware('throttle:5,1');
    Route::post('/deploy/reset', [DeployController::class, 'resetDatabase'])->middleware('throttle:5,1');
});
