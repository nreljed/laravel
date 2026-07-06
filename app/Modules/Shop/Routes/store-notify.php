<?php

use App\Modules\Shop\Http\Controllers\NotifyBackInStockController;
use Illuminate\Support\Facades\Route;

Route::post('/store/notify-back-in-stock', [NotifyBackInStockController::class, 'store'])->name('store.notify-back-in-stock');
