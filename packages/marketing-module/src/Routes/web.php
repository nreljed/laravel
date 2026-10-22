<?php

use Illuminate\Support\Facades\Route;
use Nreljed\MarketingModule\Http\Controllers\ContactsController;
use Nreljed\MarketingModule\Http\Controllers\MarketingDashboardController;
use Nreljed\MarketingModule\Http\Controllers\PostMediaController;
use Nreljed\MarketingModule\Http\Controllers\PostsController;
use Nreljed\MarketingModule\Http\Controllers\SegmentsController;
use Nreljed\MarketingModule\Http\Controllers\SocialAccountsController;
use Nreljed\MarketingModule\Http\Controllers\SocialAuthController;
use Nreljed\MarketingModule\Http\Controllers\TagsController;

// Dashboard
Route::get('/', [MarketingDashboardController::class, 'index'])->name('dashboard');

// Social accounts.
//
// The literal `connect` and `callback` segments are declared before the
// record-scoped routes so a platform key is never mistaken for a record id.
Route::get('/accounts', [SocialAccountsController::class, 'index'])->name('accounts.index');
Route::get('/accounts/connect/{platform}', [SocialAuthController::class, 'connect'])->name('accounts.connect');
Route::get('/accounts/callback/{platform}', [SocialAuthController::class, 'callback'])->name('accounts.callback');
Route::get('/accounts/{record}/edit', [SocialAccountsController::class, 'edit'])->name('accounts.edit');
Route::put('/accounts/{record}', [SocialAccountsController::class, 'update'])->name('accounts.update');
Route::delete('/accounts/{record}', [SocialAccountsController::class, 'destroy'])->name('accounts.destroy');

// Posts
//
// `account-options` feeds the composer's account picker, so it is declared
// before the record-scoped routes for the same reason `connect` is above.
Route::get('/posts', [PostsController::class, 'index'])->name('posts.index');
Route::get('/posts/create', [PostsController::class, 'create'])->name('posts.create');
Route::get('/posts/account-options', [PostsController::class, 'accountOptions'])->name('posts.account-options');
Route::post('/posts/media', [PostMediaController::class, 'upload'])->name('posts.media.upload');
Route::delete('/posts/media', [PostMediaController::class, 'destroy'])->name('posts.media.destroy');
Route::post('/posts', [PostsController::class, 'store'])->name('posts.store');
Route::get('/posts/{record}/edit', [PostsController::class, 'edit'])->name('posts.edit');
Route::put('/posts/{record}', [PostsController::class, 'update'])->name('posts.update');
Route::delete('/posts/{record}', [PostsController::class, 'destroy'])->name('posts.destroy');
Route::post('/posts/{record}/publish', [PostsController::class, 'publish'])->name('posts.publish');
Route::post('/posts/{record}/approve', [PostsController::class, 'approve'])->name('posts.approve');

// Contacts
Route::get('/contacts', [ContactsController::class, 'index'])->name('contacts.index');
Route::get('/contacts/create', [ContactsController::class, 'create'])->name('contacts.create');
Route::post('/contacts', [ContactsController::class, 'store'])->name('contacts.store');
Route::get('/contacts/{record}/edit', [ContactsController::class, 'edit'])->name('contacts.edit');
Route::put('/contacts/{record}', [ContactsController::class, 'update'])->name('contacts.update');
Route::delete('/contacts/{record}', [ContactsController::class, 'destroy'])->name('contacts.destroy');

// Tags
Route::get('/tags', [TagsController::class, 'index'])->name('tags.index');
Route::get('/tags/create', [TagsController::class, 'create'])->name('tags.create');
Route::post('/tags', [TagsController::class, 'store'])->name('tags.store');
Route::get('/tags/{record}/edit', [TagsController::class, 'edit'])->name('tags.edit');
Route::put('/tags/{record}', [TagsController::class, 'update'])->name('tags.update');
Route::delete('/tags/{record}', [TagsController::class, 'destroy'])->name('tags.destroy');

// Segments
Route::get('/segments', [SegmentsController::class, 'index'])->name('segments.index');
Route::get('/segments/create', [SegmentsController::class, 'create'])->name('segments.create');
Route::post('/segments', [SegmentsController::class, 'store'])->name('segments.store');
Route::get('/segments/{record}/edit', [SegmentsController::class, 'edit'])->name('segments.edit');
Route::put('/segments/{record}', [SegmentsController::class, 'update'])->name('segments.update');
Route::delete('/segments/{record}', [SegmentsController::class, 'destroy'])->name('segments.destroy');
