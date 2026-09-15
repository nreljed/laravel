<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\StorefrontController;

Route::get('/store', [StorefrontController::class, 'index'])->name('store.index');
Route::get('/store/products', [StorefrontController::class, 'products'])->name('store.products');
Route::get('/store/category/{slug}', [StorefrontController::class, 'category'])->name('store.category');
Route::get('/store/{product}', [StorefrontController::class, 'show'])->name('store.show');
