<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\CartController;
use Nreljed\ShopModule\Http\Controllers\CurrencySwitchController;

Route::get('/store/cart', [CartController::class, 'page'])->name('store.cart.index');
Route::get('/store/cart/json', [CartController::class, 'index'])->name('store.cart.json');
Route::post('/store/cart/items', [CartController::class, 'addItem'])->name('store.cart.add-item')->middleware('throttle:store-mutations');
Route::put('/store/cart/items/{cartItem}', [CartController::class, 'updateItem'])->name('store.cart.update-item')->middleware('throttle:store-mutations');
Route::delete('/store/cart/items/{cartItem}', [CartController::class, 'removeItem'])->name('store.cart.remove-item')->middleware('throttle:store-mutations');
Route::delete('/store/cart', [CartController::class, 'clear'])->name('store.cart.clear')->middleware('throttle:store-mutations');
Route::post('/store/cart/apply-coupon', [CartController::class, 'applyCoupon'])->name('store.cart.apply-coupon')->middleware('throttle:store-mutations');
Route::post('/store/cart/remove-coupon', [CartController::class, 'removeCoupon'])->name('store.cart.remove-coupon')->middleware('throttle:store-mutations');
Route::post('/store/currency', [CurrencySwitchController::class, 'switch'])->name('store.currency.switch')->middleware('throttle:store-mutations');
