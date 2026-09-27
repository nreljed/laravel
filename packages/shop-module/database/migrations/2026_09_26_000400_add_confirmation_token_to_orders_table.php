<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a shareable confirmation token to orders.
 *
 * Guest orders have no account to check a visitor against, so the order id was
 * the only thing standing between a stranger and someone else's name, email,
 * phone and addresses. Ids are sequential and therefore enumerable.
 *
 * The token is generated when the order is created and travels in the
 * confirmation link, which is what the shopper's own browser, their bookmark
 * and their inbox already carry. It is independent of `user_id` on purpose:
 * orders outlive the account that placed them (the FK nulls `user_id` on
 * delete), so binding access to the account would lock the owner out of their
 * own order history the moment they removed their account - which is the
 * opposite of what should happen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('confirmation_token', 64)->nullable()->unique()->after('order_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['confirmation_token']);
            $table->dropColumn('confirmation_token');
        });
    }
};
