<?php

use App\Modules\Booking\Controllers\BookingGuestController;
use Illuminate\Support\Facades\Route;

Route::get('/booking/view/{booking}', [BookingGuestController::class, 'view'])->name('booking.view');
Route::get('/booking/show/{token}', [BookingGuestController::class, 'show'])->name('booking.show');
Route::post('/booking/send-magic-link', [BookingGuestController::class, 'sendMagicLink'])->name('booking.send-magic-link');
Route::post('/booking/inline-login', [BookingGuestController::class, 'inlineLogin'])->name('booking.inline-login');
Route::post('/booking/register', [BookingGuestController::class, 'registerFromBooking'])->name('booking.register');
Route::post('/booking/cancel/{booking}', [BookingGuestController::class, 'requestCancellation'])->name('booking.cancel');
Route::post('/booking/modify/{booking}', [BookingGuestController::class, 'requestModification'])->name('booking.modify');
Route::post('/booking/confirm-cancel/{booking}', [BookingGuestController::class, 'confirmCancellation'])->name('booking.confirm-cancel');
