<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\NotifyBackInStockController;

Route::post('/store/notify-back-in-stock', [NotifyBackInStockController::class, 'store'])->name('store.notify-back-in-stock');
