<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\CartController;
use Nreljed\ShopModule\Http\Controllers\CheckoutController;
use Nreljed\ShopModule\Http\Controllers\MyAddressController;
use Nreljed\ShopModule\Http\Controllers\MyOrderController;

Route::prefix('my')->name('my.')->group(function () {
    // My Orders
    Route::get('/orders', [MyOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [MyOrderController::class, 'show'])->name('orders.show');

    // My Addresses
    Route::get('/addresses', [MyAddressController::class, 'index'])->name('addresses.index');
    Route::post('/addresses', [MyAddressController::class, 'store'])->name('addresses.store');
    Route::put('/addresses/{address}', [MyAddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [MyAddressController::class, 'destroy'])->name('addresses.destroy');
    Route::post('/addresses/{address}/primary', [MyAddressController::class, 'setPrimary'])->name('addresses.set-primary');

    // My Cart
    Route::get('/cart', [CartController::class, 'page'])->name('cart.index');
    Route::get('/cart/json', [CartController::class, 'index'])->name('cart.json');
    Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.add-item');
    Route::put('/cart/items/{cartItem}', [CartController::class, 'updateItem'])->name('cart.update-item');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'removeItem'])->name('cart.remove-item');
    Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
    Route::post('/cart/apply-coupon', [CartController::class, 'applyCoupon'])->name('cart.apply-coupon');
    Route::post('/cart/remove-coupon', [CartController::class, 'removeCoupon'])->name('cart.remove-coupon');

    // My Checkout
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/confirmation/{order}', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
    Route::post('/checkout/payment-intent', [CheckoutController::class, 'createPaymentIntent'])->name('checkout.payment-intent');
});
