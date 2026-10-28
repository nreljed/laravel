<?php

use Illuminate\Support\Facades\Route;
use Nreljed\ShopModule\Http\Controllers\StripeWebhookController;

// Payment-provider callbacks.
//
// Deliberately NOT part of the storefront group next door:
//
// * it must stay reachable while maintenance mode is on - a payment that was
//   authorised just before the doors closed still has to settle, and a 503 to
//   Stripe would leave the order stuck in `pending`;
// * the caller is Stripe's server, not a browser session, so the route is
//   CSRF-exempt (`PreventRequestForgery::except` in `ShopServiceProvider`).
//   Without that exemption every real delivery is answered with 419 and
//   asynchronously-paid orders and refunds never settle.
Route::post('/store/webhook/stripe', [StripeWebhookController::class, 'handle'])
    ->name('store.webhook.stripe');
