<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\AbandonedCartRecoveryController;

Route::get('/store/cart/recover/{cart}', [AbandonedCartRecoveryController::class, 'recover'])
    ->name('store.cart.recover')
    ->middleware('signed');
