<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->json('applied_coupons')->nullable()->after('coupon_code');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->json('applied_coupons')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('applied_coupons');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('applied_coupons');
        });
    }
};
