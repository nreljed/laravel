<?php

use Illuminate\Support\Facades\Route;
use Nreljed\DashboardCore\Blog\Controllers\PostCategoryController;
use Nreljed\DashboardCore\Blog\Controllers\PostController;
use Nreljed\DashboardCore\Blog\Controllers\PostTagController;
use Nreljed\DashboardCore\Blog\Controllers\SeoGenerationController;

// Posts
Route::get('/dashboard', [PostController::class, 'dashboard'])->name('dashboard')->middleware('permission:posts.view');
Route::get('/', [PostController::class, 'index'])->name('index')->middleware('permission:posts.view');
Route::get('/create', [PostController::class, 'create'])->name('create')->middleware('permission:posts.create');
Route::post('/', [PostController::class, 'store'])->name('store')->middleware('permission:posts.create');
Route::post('/upload-image', [PostController::class, 'uploadImage'])->name('upload-image')->middleware('permission:posts.create');
Route::delete('/delete-image', [PostController::class, 'deleteImage'])->name('delete-image')->middleware('permission:posts.edit');
Route::post('/bulk/action', [PostController::class, 'bulkAction'])->name('bulk.action')->middleware('permission:posts.delete');

// SEO Generation
Route::post('/seo/generate', [SeoGenerationController::class, 'generate'])->name('seo.generate')->middleware('permission:posts.edit');
Route::post('/seo/bulk', [SeoGenerationController::class, 'bulk'])->name('seo.bulk')->middleware('permission:posts.edit');

Route::get('/{post}/edit', [PostController::class, 'edit'])->name('edit')->middleware('permission:posts.edit');
Route::put('/{post}', [PostController::class, 'update'])->name('update')->middleware('permission:posts.edit');
Route::put('/{post}/status', [PostController::class, 'updateStatus'])->name('update-status')->middleware('permission:posts.edit');
Route::delete('/{post}', [PostController::class, 'destroy'])->name('destroy')->middleware('permission:posts.delete');

// Categories (editor+)
Route::get('/categories', [PostCategoryController::class, 'index'])->name('categories.index')->middleware('permission:manage-categories');
Route::post('/categories', [PostCategoryController::class, 'store'])->name('categories.store')->middleware('permission:manage-categories');
Route::post('/categories/reorder', [PostCategoryController::class, 'reorder'])->name('categories.reorder')->middleware('permission:manage-categories');
Route::post('/categories/bulk', [PostCategoryController::class, 'bulk'])->name('categories.bulk')->middleware('permission:manage-categories');
Route::put('/categories/{postCategory}', [PostCategoryController::class, 'update'])->name('categories.update')->middleware('permission:manage-categories');
Route::delete('/categories/{postCategory}', [PostCategoryController::class, 'destroy'])->name('categories.destroy')->middleware('permission:manage-categories');

// Tags (editor+)
Route::get('/tags', [PostTagController::class, 'index'])->name('tags.index')->middleware('permission:manage-tags');
Route::post('/tags', [PostTagController::class, 'store'])->name('tags.store')->middleware('permission:manage-tags');
Route::post('/tags/reorder', [PostTagController::class, 'reorder'])->name('tags.reorder')->middleware('permission:manage-tags');
Route::post('/tags/bulk', [PostTagController::class, 'bulk'])->name('tags.bulk')->middleware('permission:manage-tags');
Route::put('/tags/{postTag}', [PostTagController::class, 'update'])->name('tags.update')->middleware('permission:manage-tags');
Route::delete('/tags/{postTag}', [PostTagController::class, 'destroy'])->name('tags.destroy')->middleware('permission:manage-tags');
