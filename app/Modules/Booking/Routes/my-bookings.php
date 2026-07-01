<?php

use App\Modules\Booking\Controllers\BookingGuestController;
use Illuminate\Support\Facades\Route;

Route::get('/my-bookings', [BookingGuestController::class, 'myBookings'])->name('my-bookings.index');
Route::get('/my-bookings/{bookingId}', [BookingGuestController::class, 'show'])->name('my-bookings.show');
Route::post('/my-bookings/cancel/{bookingId}', [BookingGuestController::class, 'loggedInCancel'])->name('my-bookings.cancel');
Route::post('/my-bookings/modify/{bookingId}', [BookingGuestController::class, 'loggedInModify'])->name('my-bookings.modify');
