<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\ShopSettingsController;

Route::get('/settings', [ShopSettingsController::class, 'index'])->name('settings.index');
Route::put('/settings', [ShopSettingsController::class, 'update'])->name('settings.update');
