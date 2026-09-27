<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\ReviewsController;
use Nreljed\ShopModule\Http\Controllers\StorefrontController;

Route::get('/store', [StorefrontController::class, 'index'])->name('store.index');
Route::get('/store/products', [StorefrontController::class, 'products'])->name('store.products');
Route::get('/store/category/{slug}', [StorefrontController::class, 'category'])->name('store.category');

// Customer review submission, deliberately kept here rather than reusing the
// dashboard route: `Routes/web.php` sits behind `permission:shop.manage`, so a
// shopper reading reviews on the storefront had no way to write one. Auth and
// verification are per-route because the storefront group is public, which
// matches the wishlist action beside it. Whether the review may attach a
// qualifying order, and the duplicate check, stay in the controller.
Route::post('/store/products/{product}/reviews', [ReviewsController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('store.reviews.store');

Route::get('/store/{product}', [StorefrontController::class, 'show'])->name('store.show');
