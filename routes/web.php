<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ═══════════════════════════════════════════════════
// PROJECT ROUTES
// ═══════════════════════════════════════════════════
//
// The public frontend, deploy, csp-report, dashboard, admin, auth, and
// maintenance routes are provided by the nreljed/platform package. This file
// is reserved for project-specific routes. Features register their own
// routes through their feature service providers.
//
// Route::get('/example', fn () => view('example'))->name('example');

Route::get('/style-guide', function () {
    return Inertia::render('public/styleguide');
})->name('styleguide');
