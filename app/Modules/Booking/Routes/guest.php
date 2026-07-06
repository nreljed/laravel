<?php

use App\Modules\Booking\Controllers\BookingGuestController;
use Illuminate\Support\Facades\Route;

Route::get('/booking/view', [BookingGuestController::class, 'view'])->name('booking.view');
Route::get('/booking/show/{bookingId}', [BookingGuestController::class, 'show'])->name('booking.show');
Route::post('/booking/send-magic-link', [BookingGuestController::class, 'sendMagicLink'])->name('booking.send-magic-link');
Route::post('/booking/inline-login', [BookingGuestController::class, 'inlineLogin'])->name('booking.inline-login');
Route::post('/booking/register', [BookingGuestController::class, 'registerFromBooking'])->name('booking.register');
Route::post('/booking/cancel/{bookingId}', [BookingGuestController::class, 'requestCancellation'])->name('booking.cancel');
Route::post('/booking/modify/{bookingId}', [BookingGuestController::class, 'requestModification'])->name('booking.modify');
Route::get('/booking/confirm-cancel/{bookingId}', [BookingGuestController::class, 'confirmCancellation'])->name('booking.confirm-cancel');
Route::post('/booking/check-in/{bookingId}', [BookingGuestController::class, 'guestCheckIn'])->name('booking.check-in');
Route::post('/booking/check-out/{bookingId}', [BookingGuestController::class, 'guestCheckOut'])->name('booking.check-out');
