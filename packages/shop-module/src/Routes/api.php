<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\Api\ProductsController;

Route::get('/products', [ProductsController::class, 'index'])->name('api.products.index');
Route::get('/products/{product}', [ProductsController::class, 'show'])->name('api.products.show');
