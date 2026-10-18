<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
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

// Throttled per IP so the public form cannot be used to flood the admin inbox.
Route::post('/contact', [PublicController::class, 'submit'])
    ->middleware('throttle:3,1')
    ->name('contact.store');
