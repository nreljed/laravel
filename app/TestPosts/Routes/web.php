<?php

use App\TestPosts\Controllers\TestPostController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [TestPostController::class, 'dashboard'])->name('dashboard');
Route::get('/', [TestPostController::class, 'index'])->name('index');
Route::get('/create', [TestPostController::class, 'create'])->name('create');
Route::post('/', [TestPostController::class, 'store'])->name('store');
Route::post('/upload-image', [TestPostController::class, 'uploadImage'])->name('upload-image');
Route::delete('/delete-image', [TestPostController::class, 'deleteImage'])->name('delete-image');
Route::post('/bulk/action', [TestPostController::class, 'bulkAction'])->name('bulk.action');
Route::get('/{testPost}/edit', [TestPostController::class, 'edit'])->name('edit');
Route::put('/{testPost}', [TestPostController::class, 'update'])->name('update');
Route::put('/{testPost}/status', [TestPostController::class, 'updateStatus'])->name('update-status');
Route::delete('/{testPost}', [TestPostController::class, 'destroy'])->name('destroy');
