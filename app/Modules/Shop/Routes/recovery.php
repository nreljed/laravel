<?php

use App\Modules\Shop\Http\Controllers\AbandonedCartRecoveryController;
use Illuminate\Support\Facades\Route;

Route::get('/store/cart/recover/{cart}', [AbandonedCartRecoveryController::class, 'recover'])
    ->name('store.cart.recover')
    ->middleware('signed');
