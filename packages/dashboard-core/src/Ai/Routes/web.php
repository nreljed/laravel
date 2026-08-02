<?php

use Illuminate\Support\Facades\Route;
use Nreljed\DashboardCore\Ai\Controllers\AiProviderController;

Route::middleware(['auth', 'verified'])->prefix('dashboard/account')->name('dashboard.account.')->group(function () {
    Route::get('/ai-keys', [AiProviderController::class, 'index'])->name('ai-keys.index');
    Route::post('/ai-keys', [AiProviderController::class, 'store'])->name('ai-keys.store');
    Route::put('/ai-keys/{aiProvider:id}', [AiProviderController::class, 'update'])->name('ai-keys.update');
    Route::delete('/ai-keys/{aiProvider:id}', [AiProviderController::class, 'destroy'])->name('ai-keys.destroy');
    Route::post('/ai-keys/test', [AiProviderController::class, 'test'])->name('ai-keys.test');
});
