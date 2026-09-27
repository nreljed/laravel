<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anchors a provisional (pre-payment) order to the cart it was quoted from.
 *
 * `createPaymentIntent()` writes a draft order before the browser talks to
 * Stripe, and checkout later claims that draft by `payment_intent_id`. With no
 * link back to the cart, nothing tied the draft to the person who created it -
 * `payment_intent_id` is client-supplied, so any shopper who learned another
 * shopper's intent could have their own cart, addresses and email written onto
 * that stranger's draft order.
 *
 * The cart is the correct anchor: it is resolved from the session or the
 * authenticated user, so matching on it establishes ownership for guests and
 * members alike.
 *
 * Nullable and additive - existing orders keep a NULL cart_id, and the
 * checkout falls back to the user_id check for those.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('cart_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cart_id');
        });
    }
};
