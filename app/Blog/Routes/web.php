<?php

use App\Blog\Controllers\PostCategoryController;
use App\Blog\Controllers\PostController;
use App\Blog\Controllers\PostTagController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [PostController::class, 'dashboard'])->name('dashboard');
Route::get('/', [PostController::class, 'index'])->name('index');
Route::get('/create', [PostController::class, 'create'])->name('create');
Route::post('/', [PostController::class, 'store'])->name('store');
Route::get('/{post}/edit', [PostController::class, 'edit'])->name('edit');
Route::put('/{post}', [PostController::class, 'update'])->name('update');
Route::delete('/{post}', [PostController::class, 'destroy'])->name('destroy');
Route::post('/upload-image', [PostController::class, 'uploadImage'])->name('upload-image');
Route::delete('/delete-image', [PostController::class, 'deleteImage'])->name('delete-image');

// Categories
Route::get('/categories', [PostCategoryController::class, 'index'])->name('categories.index');
Route::post('/categories', [PostCategoryController::class, 'store'])->name('categories.store');
Route::put('/categories/{postCategory}', [PostCategoryController::class, 'update'])->name('categories.update');
Route::delete('/categories/{postCategory}', [PostCategoryController::class, 'destroy'])->name('categories.destroy');

// Tags
Route::get('/tags', [PostTagController::class, 'index'])->name('tags.index');
Route::post('/tags', [PostTagController::class, 'store'])->name('tags.store');
Route::put('/tags/{postTag}', [PostTagController::class, 'update'])->name('tags.update');
Route::delete('/tags/{postTag}', [PostTagController::class, 'destroy'])->name('tags.destroy');
