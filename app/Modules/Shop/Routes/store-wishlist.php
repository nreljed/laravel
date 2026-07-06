<?php

use App\Modules\Shop\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/store/wishlist', [WishlistController::class, 'index'])->name('store.wishlist.index');
Route::post('/store/wishlist/toggle/{product}', [WishlistController::class, 'toggle'])->name('store.wishlist.toggle')->middleware('auth');
Route::get('/api/wishlist/ids', [WishlistController::class, 'ids'])->name('store.wishlist.ids')->middleware('auth');
