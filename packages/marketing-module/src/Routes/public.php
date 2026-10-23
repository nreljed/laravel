<?php

use Illuminate\Support\Facades\Route;
use Nreljed\MarketingModule\Http\Controllers\LinkRedirectController;

Route::get('/{code}', LinkRedirectController::class)
    ->where('code', '[A-Za-z0-9]{4,16}')
    ->name('redirect');
