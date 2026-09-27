<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\Api\ProductsController;

/*
 * The catalog API is unauthenticated and pageable, so without a limit it is a
 * bulk export: walk the page cursor with a large per_page and the whole
 * catalog comes out at whatever rate the database will serve it. 60/min per IP
 * is far above any real storefront client's polling, and still bounds the
 * scrape.
 */
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/products', [ProductsController::class, 'index'])->name('api.products.index');
    Route::get('/products/{product}', [ProductsController::class, 'show'])->name('api.products.show');
});
