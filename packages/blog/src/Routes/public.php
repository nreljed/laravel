<?php

use Illuminate\Support\Facades\Route;
use Nreljed\Blog\Controllers\BlogPublicController;

Route::get('/', [BlogPublicController::class, 'index'])->name('blog.index');
Route::get('/preview/{post}', [BlogPublicController::class, 'preview'])
    ->name('blog.preview')
    ->middleware('signed');
Route::get('/{slug}', [BlogPublicController::class, 'show'])->name('blog.show');
