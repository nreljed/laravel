<?php

use App\Modules\ECommerce\Http\Controllers\ECommerceSettingsController;
use Illuminate\Support\Facades\Route;

Route::put('/settings', [ECommerceSettingsController::class, 'update'])->name('settings.update');
