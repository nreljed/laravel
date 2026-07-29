<?php

use App\TestPosts\Controllers\TestPostPublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TestPostPublicController::class, 'index'])->name('index');
Route::get('/{slug}', [TestPostPublicController::class, 'show'])->name('show');
