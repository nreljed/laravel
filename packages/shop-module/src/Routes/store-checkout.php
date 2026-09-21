<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\CheckoutController;
use Nreljed\ShopModule\Http\Controllers\StripeWebhookController;

Route::get('/store/checkout', [CheckoutController::class, 'index'])->name('store.checkout.index');
Route::post('/store/checkout', [CheckoutController::class, 'store'])->name('store.checkout.store');
Route::get('/store/checkout/confirmation/{order}', [CheckoutController::class, 'confirmation'])->name('store.checkout.confirmation');
Route::post('/store/checkout/payment-intent', [CheckoutController::class, 'createPaymentIntent'])->name('store.checkout.payment-intent');

// Guest registration from order (signed URL)
Route::get('/store/order/{order}/register', [CheckoutController::class, 'registerFromOrder'])->name('store.checkout.register-from-order');
Route::post('/store/order/{order}/register', [CheckoutController::class, 'storeRegisterFromOrder'])->name('store.checkout.store-register');

// Stripe webhook (no auth)
Route::post('/store/webhook/stripe', [StripeWebhookController::class, 'handle'])->name('store.webhook.stripe');
