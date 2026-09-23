<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 6)->default(1.0)->after('currency');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('unit_price');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('discount');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
