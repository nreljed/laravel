<?php

use Illuminate\Support\Facades\Route;
use Nreljed\DashboardCore\Blog\Controllers\BlogPublicController;

Route::get('/', [BlogPublicController::class, 'index'])->name('blog.index');
Route::get('/{slug}', [BlogPublicController::class, 'show'])->name('blog.show');
