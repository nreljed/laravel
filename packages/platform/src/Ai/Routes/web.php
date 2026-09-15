<?php

use Illuminate\Support\Facades\Route;
use Nreljed\Platform\Ai\Controllers\AiProviderController;

/*
|--------------------------------------------------------------------------
| AI Provider Keys (BYOK) — Account → AI Keys
|--------------------------------------------------------------------------
|
| Loaded from AiServiceProvider::boot() behind the web/auth/maintenance
| middleware group anchored at /dashboard/account with the
| dashboard.account.ai-keys. route-name prefix (hence the bare route names
| below — the group prefix supplies the rest).
|
*/

Route::get('/ai-keys', [AiProviderController::class, 'index'])->name('index');
Route::post('/ai-keys', [AiProviderController::class, 'store'])->name('store');
Route::post('/ai-keys/test', [AiProviderController::class, 'test'])->name('test');
Route::get('/ai-keys/usage', [AiProviderController::class, 'usage'])->name('usage');
Route::put('/ai-keys/usage-cap', [AiProviderController::class, 'usageCap'])->name('usage-cap');
Route::put('/ai-keys/{aiProvider}', [AiProviderController::class, 'update'])->name('update');
Route::delete('/ai-keys/{aiProvider}', [AiProviderController::class, 'destroy'])->name('destroy');
