<?php

use App\Modules\ECommerce\Http\Controllers\CartController;
use App\Modules\ECommerce\Http\Controllers\CustomersController;
use App\Modules\ECommerce\Http\Controllers\OrdersController;
use App\Modules\ECommerce\Http\Controllers\ProductsController;
use Illuminate\Support\Facades\Route;

// Products
Route::get('/products', [ProductsController::class, 'index'])->name('products.index');
Route::get('/products/create', [ProductsController::class, 'create'])->name('products.create');
Route::post('/products', [ProductsController::class, 'store'])->name('products.store');
Route::get('/products/{product}/edit', [ProductsController::class, 'edit'])->name('products.edit');
Route::put('/products/{product}', [ProductsController::class, 'update'])->name('products.update');
Route::delete('/products/{product}', [ProductsController::class, 'destroy'])->name('products.destroy');
Route::post('/products/upload-image', [ProductsController::class, 'uploadImage'])->name('products.upload-image');
Route::delete('/products/delete-image', [ProductsController::class, 'deleteImage'])->name('products.delete-image');

// Orders
Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
Route::get('/orders/{order}', [OrdersController::class, 'show'])->name('orders.show');
Route::patch('/orders/{order}/status', [OrdersController::class, 'updateStatus'])->name('orders.update-status');

// Customers
Route::get('/customers', [CustomersController::class, 'index'])->name('customers.index');

// Cart API
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.add-item');
Route::put('/cart/items/{cartItem}', [CartController::class, 'updateItem'])->name('cart.update-item');
Route::delete('/cart/items/{cartItem}', [CartController::class, 'removeItem'])->name('cart.remove-item');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
