<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which shipping method an order was bought with.
 *
 * `shipping_total` says how much was charged for shipping but not what was
 * bought, so "which carrier went wrong" could only be answered by matching
 * amounts against the current shipping table - which fails the moment a method
 * is deleted, and silently misattributes the order as soon as one is renamed or
 * repriced. Neither is acceptable for the record of a sale that has already
 * happened.
 *
 * `shipping_method_id` is nullable and nulls on delete: orders outlive the
 * catalogue that priced them, and deleting a shipping method must not take a
 * sale with it. `shipping_method_name` is snapshotted alongside it so the
 * answer survives that deletion - the id is the join, the name is the fact.
 *
 * Both are nullable because orders placed before this migration have neither,
 * and a historical order is not retroactively a shipping-method order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_method_id')
                ->nullable()
                ->after('shipping_total')
                ->constrained('shipping_methods')
                ->nullOnDelete();

            $table->string('shipping_method_name')->nullable()->after('shipping_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn('shipping_method_name');
        });
    }
};
