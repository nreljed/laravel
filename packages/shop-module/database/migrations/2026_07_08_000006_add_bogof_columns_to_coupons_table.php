<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('buy_qty')->nullable()->after('auto_discount_label');
            $table->unsignedInteger('free_qty')->nullable()->after('buy_qty');
            $table->unsignedBigInteger('free_product_id')->nullable()->after('free_qty');

            $table->foreign('free_product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropForeign(['free_product_id']);
            $table->dropColumn(['buy_qty', 'free_qty', 'free_product_id']);
        });
    }
};
