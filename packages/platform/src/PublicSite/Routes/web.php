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

/*
|--------------------------------------------------------------------------
| Form submission collect endpoints
|--------------------------------------------------------------------------
|
| Every declared form is collectable at POST /forms/{form_key}, with the
| contact form additionally reachable at POST /contact for the existing
| public page. Both throttle per form via the `public-form` named limiter,
| which reads the form's `rate_limit` from config at request time.
|
*/

Route::post('/contact', [PublicController::class, 'submit'])
    ->defaults('form_key', 'contact')
    ->middleware('throttle:public-form')
    ->name('contact.store');

Route::post('/forms/{form_key}', [PublicController::class, 'submit'])
    ->middleware('throttle:public-form')
    ->name('forms.store');
