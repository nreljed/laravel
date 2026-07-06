<?php

use App\Modules\Booking\Controllers\BookingAjaxController;
use App\Modules\Booking\Controllers\BookingAvailabilityController;
use App\Modules\Booking\Controllers\BookingController;
use App\Modules\Booking\Controllers\BookingDemoController;
use App\Modules\Booking\Controllers\BookingGlobalSettingsController;
use App\Modules\Booking\Controllers\BookingGuestController;
use App\Modules\Booking\Controllers\BookingIcalController;
use App\Modules\Booking\Controllers\BookingSystemController;
use Illuminate\Support\Facades\Route;

// ─── Dashboard ──────────────────────────────────────────
Route::get('/dashboard', [BookingController::class, 'dashboard'])->name('dashboard');

// ─── CRUD ───────────────────────────────────────────────
Route::get('/', [BookingController::class, 'index'])->name('index');
Route::get('/create', [BookingController::class, 'create'])->name('create');
Route::post('/', [BookingController::class, 'store'])->name('store');
Route::post('/calculate-price', [BookingController::class, 'calculatePrice'])->name('calculate-price');
Route::post('/bulk', [BookingController::class, 'bulkTransition'])->name('bulk');
Route::get('/export/csv', [BookingController::class, 'exportCsv'])->name('export.csv');

// ─── Calendar ───────────────────────────────────────────
Route::get('/calendar/data', [BookingController::class, 'calendar'])->name('calendar');

// ─── iCal Feeds ─────────────────────────────────────────
Route::post('/ical/import', [BookingIcalController::class, 'import'])->name('ical.import');
Route::get('/ical/feeds', [BookingIcalController::class, 'feeds'])->name('ical.feeds');
Route::post('/ical/feeds/{feedId}/sync', [BookingIcalController::class, 'sync'])->name('ical.sync');
Route::delete('/ical/feeds/{feedId}', [BookingIcalController::class, 'destroy'])->name('ical.destroy');

// ─── Availability ───────────────────────────────────────
Route::get('/availability', [BookingController::class, 'availability'])->name('availability');
Route::get('/availability/{propertyId}', [BookingAvailabilityController::class, 'check'])->name('availability.check');
Route::post('/availability/block', [BookingAvailabilityController::class, 'block'])->name('availability.block');
Route::post('/availability/unblock/{id}', [BookingAvailabilityController::class, 'unblock'])->name('availability.unblock');
Route::get('/availability/cache-version/{propertyId}', [BookingAvailabilityController::class, 'cacheVersion'])->name('availability.cache-version');
Route::post('/availability/check-api', [BookingAvailabilityController::class, 'checkAvailabilityApi'])->name('availability.check-api');
Route::post('/availability/clear-opcache', [BookingAvailabilityController::class, 'clearOpcache'])->name('availability.clear-opcache');

// ─── Block Dates (manual) ───────────────────────────────
Route::post('/block-dates', [BookingController::class, 'blockDates'])->name('block-dates');

// ─── Archive ────────────────────────────────────────────
Route::post('/archive/{booking}', [BookingController::class, 'toggleArchive'])->name('archive');

// ─── Global Booking Settings ───────────────────────────
Route::get('/global-settings', [BookingGlobalSettingsController::class, 'edit'])->name('global-settings.edit');
Route::put('/global-settings', [BookingGlobalSettingsController::class, 'update'])->name('global-settings.update');

// ─── Demo ───────────────────────────────────────────────
Route::get('/demo', [BookingDemoController::class, 'demo'])->name('demo');
Route::post('/demo/seed', [BookingDemoController::class, 'seed'])->name('demo.seed');
Route::post('/demo/cleanup', [BookingDemoController::class, 'cleanupAll'])->name('demo.cleanup');
Route::get('/demo/{id}', [BookingDemoController::class, 'demoResult'])->name('demo.result');
Route::get('/demo/{id}/magic-link', [BookingDemoController::class, 'generateMagicLink'])->name('demo.magic-link');
Route::get('/demo/{id}/token-status', [BookingDemoController::class, 'tokenStatus'])->name('demo.token-status');
Route::get('/demo/{id}/email-status', [BookingDemoController::class, 'emailStatus'])->name('demo.email-status');
Route::get('/demo/{id}/pipeline-trace', [BookingDemoController::class, 'pipelineTrace'])->name('demo.pipeline-trace');
Route::get('/demo/{id}/verify', [BookingDemoController::class, 'verifyIntegration'])->name('demo.verify');
Route::get('/demo/diagnose', [BookingDemoController::class, 'diagnose'])->name('demo.diagnose');

// ─── Guest Portal (logged-in-only routes; public guest routes are in guest.php) ──
Route::prefix('/guest')->name('guest.')->group(function () {
    Route::post('/logged-in-modify/{bookingId}', [BookingGuestController::class, 'loggedInModify'])->name('logged-in-modify');
    Route::post('/logged-in-cancel/{bookingId}', [BookingGuestController::class, 'loggedInCancel'])->name('logged-in-cancel');
    Route::post('/logged-in-check-in/{bookingId}', [BookingGuestController::class, 'loggedInCheckIn'])->name('logged-in-check-in');
    Route::post('/logged-in-check-out/{bookingId}', [BookingGuestController::class, 'loggedInCheckOut'])->name('logged-in-check-out');
    Route::post('/toggle-mode', [BookingGuestController::class, 'toggleMode'])->name('toggle-mode');
});

// ─── System ─────────────────────────────────────────────
Route::post('/system/transitions', [BookingSystemController::class, 'runTransitions'])->name('system.transitions');
Route::get('/system/status', [BookingSystemController::class, 'status'])->name('system.status');

// ─── AJAX ───────────────────────────────────────────────
Route::post('/ajax/damage-photo/upload', [BookingAjaxController::class, 'uploadDamagePhoto'])->name('ajax.damage-photo.upload');
Route::post('/ajax/damage-photo/delete', [BookingAjaxController::class, 'deleteDamagePhoto'])->name('ajax.damage-photo.delete');

// ─── Booking Actions ────────────────────────────────────
Route::post('/{booking}/cancel', [BookingController::class, 'cancel'])->name('cancel');
Route::post('/{booking}/deposit/collect', [BookingController::class, 'markDepositCollected'])->name('deposit.collect');
Route::post('/{booking}/deposit/refund', [BookingController::class, 'markDepositRefunded'])->name('deposit.refund');
Route::post('/{booking}/deposit/claim', [BookingController::class, 'markDepositClaimed'])->name('deposit.claim');
Route::post('/{booking}/mark-paid', [BookingController::class, 'markPaid'])->name('mark-paid');
Route::post('/{booking}/policy-override', [BookingController::class, 'policyOverride'])->name('policy-override');
Route::post('/{booking}/request-review', [BookingController::class, 'requestReview'])->name('request-review');

// ─── Parameterized CRUD (must be last) ──────────────────
Route::get('/{booking}/edit', [BookingController::class, 'edit'])->name('edit');
Route::put('/{booking}', [BookingController::class, 'update'])->name('update');
Route::get('/{booking}', [BookingController::class, 'show'])->name('show');
Route::delete('/{booking}', [BookingController::class, 'destroy'])->name('destroy');
Route::post('/{booking}/transition', [BookingController::class, 'transition'])->name('transition');
