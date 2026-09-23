<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_group_id')->nullable()->after('auto_discount_label');
            $table->boolean('exclude_sale_items')->default(false)->after('customer_group_id');
            $table->index('customer_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['customer_group_id']);
            $table->dropColumn(['customer_group_id', 'exclude_sale_items']);
        });
    }
};
