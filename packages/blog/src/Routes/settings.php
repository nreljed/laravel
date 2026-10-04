<?php

use Illuminate\Support\Facades\Route;
use Nreljed\Blog\Controllers\PostsSettingsController;

// Posts feature settings (unified admin settings page).
Route::get('/', [PostsSettingsController::class, 'index'])->name('index')->middleware('permission:manage-settings');
Route::put('/', [PostsSettingsController::class, 'update'])->name('update')->middleware('permission:manage-settings');
