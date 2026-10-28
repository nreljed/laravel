<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Nreljed\Platform\PublicSite\Controllers\PublicController;

/*
|--------------------------------------------------------------------------
| Public Site Routes — no auth required
|--------------------------------------------------------------------------
|
| Loaded from PublicSiteServiceProvider::boot() behind the web/maintenance
| middleware group. Template page components are provided by the starter
| theme (resources/themes/starter) or overridden per project.
|
*/

Route::get('/', fn () => Inertia::render('public/welcome', [
    'canLogin' => Route::has('login'),
    'canRegister' => Route::has('register'),
]))->name('home');

Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/services', [PublicController::class, 'services'])->name('services');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');