<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('sale_price', 10, 2)->nullable()->after('compare_price');
            $table->timestamp('sale_from')->nullable()->after('sale_price');
            $table->timestamp('sale_to')->nullable()->after('sale_from');
            $table->timestamp('publish_date')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sale_price', 'sale_from', 'sale_to', 'publish_date']);
        });
    }
};
