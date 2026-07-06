<?php

use App\Modules\Booking\Controllers\BookingGuestController;
use Illuminate\Support\Facades\Route;

Route::get('/my-bookings', [BookingGuestController::class, 'myBookings'])->name('my-bookings.index');
Route::get('/my-bookings/{bookingId}', [BookingGuestController::class, 'show'])->name('my-bookings.show');
Route::post('/my-bookings/cancel/{bookingId}', [BookingGuestController::class, 'loggedInCancel'])->name('my-bookings.cancel');
Route::post('/my-bookings/modify/{bookingId}', [BookingGuestController::class, 'loggedInModify'])->name('my-bookings.modify');
Route::post('/my-bookings/check-in/{bookingId}', [BookingGuestController::class, 'loggedInCheckIn'])->name('my-bookings.check-in');
Route::post('/my-bookings/check-out/{bookingId}', [BookingGuestController::class, 'loggedInCheckOut'])->name('my-bookings.check-out');
