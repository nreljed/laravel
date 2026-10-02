<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_variations', 'visible')) {
            Schema::table('product_variations', function (Blueprint $table) {
                $table->boolean('visible')->default(true)->after('is_active');
            });
        }

        // Backfill: visible = true for variations that have an active_price,
        // false for those without (price-less placeholder variations)
        DB::statement('UPDATE product_variations SET visible = 1 WHERE active_price IS NOT NULL');
        DB::statement('UPDATE product_variations SET visible = 0 WHERE active_price IS NULL');
    }

    public function down(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn('visible');
        });
    }
};
