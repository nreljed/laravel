<?php

use App\Modules\Posts\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PostController::class, 'index'])->name('index');
Route::get('/create', [PostController::class, 'create'])->name('create');
Route::post('/', [PostController::class, 'store'])->name('store');
Route::get('/{post}/edit', [PostController::class, 'edit'])->name('edit');
Route::put('/{post}', [PostController::class, 'update'])->name('update');
Route::delete('/{post}', [PostController::class, 'destroy'])->name('destroy');
