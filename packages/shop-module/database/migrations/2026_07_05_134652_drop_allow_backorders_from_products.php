<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('allow_backorders');
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn('allow_backorders');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('allow_backorders')->default(false)->after('track_stock');
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->boolean('allow_backorders')->default(false)->after('track_stock');
        });
    }
};
