<?php

use App\Blog\Controllers\BlogPublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BlogPublicController::class, 'index'])->name('blog.index');
Route::get('/{slug}', [BlogPublicController::class, 'show'])->name('blog.show');
