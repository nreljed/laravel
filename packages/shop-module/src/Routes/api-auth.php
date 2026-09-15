<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\Api\OrdersController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrdersController::class, 'index'])->name('api.orders.index');
    Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('api.orders.show');
});
