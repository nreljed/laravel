<?php

use App\Modules\Properties\Controllers\PropertyController;
use App\Modules\Properties\Controllers\PropertyTermController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PropertyController::class, 'index'])->name('index');
Route::get('/create', [PropertyController::class, 'create'])->name('create');
Route::post('/', [PropertyController::class, 'store'])->name('store');
Route::get('/{property}/edit', [PropertyController::class, 'edit'])->name('edit');
Route::put('/{property}', [PropertyController::class, 'update'])->name('update');
Route::delete('/{property}', [PropertyController::class, 'destroy'])->name('destroy');

Route::post('/{property}/images/upload', [PropertyController::class, 'uploadImage'])->name('upload-image');
Route::delete('/{property}/images/{image}', [PropertyController::class, 'deleteImage'])->name('delete-image');
Route::post('/{property}/images/reorder', [PropertyController::class, 'reorderImages'])->name('reorder-images');
Route::post('/{property}/images/{image}/featured', [PropertyController::class, 'setFeaturedImage'])->name('set-featured-image');

Route::prefix('/terms')->name('terms.')->group(function () {
    Route::get('/types', [PropertyTermController::class, 'indexTypes'])->name('types.index');
    Route::post('/types', [PropertyTermController::class, 'storeType'])->name('types.store');
    Route::put('/types/{type}', [PropertyTermController::class, 'updateType'])->name('types.update');
    Route::delete('/types/{type}', [PropertyTermController::class, 'destroyType'])->name('types.destroy');

    Route::get('/categories', [PropertyTermController::class, 'indexCategories'])->name('categories.index');
    Route::post('/categories', [PropertyTermController::class, 'storeCategory'])->name('categories.store');
    Route::put('/categories/{category}', [PropertyTermController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{category}', [PropertyTermController::class, 'destroyCategory'])->name('categories.destroy');

    Route::get('/features', [PropertyTermController::class, 'indexFeatures'])->name('features.index');
    Route::post('/features', [PropertyTermController::class, 'storeFeature'])->name('features.store');
    Route::put('/features/{feature}', [PropertyTermController::class, 'updateFeature'])->name('features.update');
    Route::delete('/features/{feature}', [PropertyTermController::class, 'destroyFeature'])->name('features.destroy');

    Route::get('/statuses', [PropertyTermController::class, 'indexStatuses'])->name('statuses.index');
    Route::post('/statuses', [PropertyTermController::class, 'storeStatus'])->name('statuses.store');
    Route::put('/statuses/{status}', [PropertyTermController::class, 'updateStatus'])->name('statuses.update');
    Route::delete('/statuses/{status}', [PropertyTermController::class, 'destroyStatus'])->name('statuses.destroy');
});
