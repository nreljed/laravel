<?php

use App\Modules\Shop\Http\Controllers\ShopSettingsController;
use Illuminate\Support\Facades\Route;

Route::put('/settings', [ShopSettingsController::class, 'update'])->name('settings.update');
