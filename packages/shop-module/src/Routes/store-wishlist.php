<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\WishlistController;

Route::get('/store/wishlist', [WishlistController::class, 'index'])->name('store.wishlist.index');
Route::post('/store/wishlist/toggle/{product}', [WishlistController::class, 'toggle'])->name('store.wishlist.toggle')->middleware('auth');
Route::get('/api/wishlist/ids', [WishlistController::class, 'ids'])->name('store.wishlist.ids')->middleware('auth');
