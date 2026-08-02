<?php

use App\Http\Controllers\DeployController;
use App\Http\Controllers\PublicController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ═══════════════════════════════════════════════════
// FRONTEND (Public) Pages — no auth required
// ═══════════════════════════════════════════════════
Route::middleware('maintenance')->group(function () {
    Route::get('/', fn () => Inertia::render('public/welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]))->name('home');

    Route::get('/about', [PublicController::class, 'about'])->name('about');
    Route::get('/services', [PublicController::class, 'services'])->name('services');
    Route::get('/contact', [PublicController::class, 'contact'])->name('contact');
});

// ═══════════════════════════════════════════════════
// DEPLOY Endpoints — token-protected, no CSRF, POST-only, rate-limited
// ═══════════════════════════════════════════════════
Route::post('/deploy/upload', [DeployController::class, 'upload'])->middleware('throttle:5,1');
Route::post('/deploy/cache-clear', [DeployController::class, 'cacheClear'])->middleware('throttle:5,1');
Route::post('/deploy/check', [DeployController::class, 'check'])->middleware('throttle:5,1');
Route::post('/deploy/migrate', [DeployController::class, 'migrate'])->middleware('throttle:5,1');
Route::post('/deploy/create-admin', [DeployController::class, 'createAdmin'])->middleware('throttle:5,1');

// ═══════════════════════════════════════════════════
// CSP Report Endpoint — receives violation reports
// ═══════════════════════════════════════════════════
Route::post('/csp-report', function (Request $request) {
    report(new Exception('CSP Violation: '.$request->input('csp-report.effective-directive', 'unknown')));

    return response()->json(['status' => 'ok']);
})->middleware('throttle:30,1')->name('csp.report');
