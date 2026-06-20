<?php

use App\Modules\Booking\Controllers\BookingGuestController;
use Illuminate\Support\Facades\Route;

Route::get('/my-bookings', [BookingGuestController::class, 'myBookings'])->name('my-bookings.index');
Route::get('/my-bookings/{booking}', [BookingGuestController::class, 'show'])->name('my-bookings.show');
Route::post('/my-bookings/cancel/{booking}', [BookingGuestController::class, 'loggedInCancel'])->name('my-bookings.cancel');
Route::post('/my-bookings/modify/{booking}', [BookingGuestController::class, 'loggedInModify'])->name('my-bookings.modify');
