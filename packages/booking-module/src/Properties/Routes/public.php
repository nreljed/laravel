<?php

use Illuminate\Support\Facades\Route;
use Nreljed\BookingModule\Properties\Controllers\PublicPropertyController;

Route::get('/properties', [PublicPropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{slug}', [PublicPropertyController::class, 'show'])->name('properties.show');
