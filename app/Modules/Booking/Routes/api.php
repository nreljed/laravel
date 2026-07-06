<?php

use App\Modules\Booking\Controllers\BookingAvailabilityController;
use App\Modules\Booking\Controllers\BookingGuestController;
use Illuminate\Support\Facades\Route;

Route::post('/booking/check-availability', [BookingAvailabilityController::class, 'checkAvailabilityApi'])->name('booking.check-availability');
Route::get('/booking/cache-version/{propertyId}', [BookingAvailabilityController::class, 'cacheVersion'])->name('booking.cache-version');
Route::post('/booking/modify/{bookingId}', [BookingGuestController::class, 'requestModification'])->name('booking.modify');
Route::post('/booking/cancel/{bookingId}', [BookingGuestController::class, 'requestCancellation'])->name('booking.cancel');
