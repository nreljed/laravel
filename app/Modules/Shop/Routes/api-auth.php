<?php

use App\Modules\Shop\Http\Controllers\Api\OrdersController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrdersController::class, 'index'])->name('api.orders.index');
    Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('api.orders.show');
});
