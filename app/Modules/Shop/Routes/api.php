<?php

use App\Modules\Shop\Http\Controllers\Api\ProductsController;
use Illuminate\Support\Facades\Route;

Route::get('/products', [ProductsController::class, 'index'])->name('api.products.index');
Route::get('/products/{product}', [ProductsController::class, 'show'])->name('api.products.show');
