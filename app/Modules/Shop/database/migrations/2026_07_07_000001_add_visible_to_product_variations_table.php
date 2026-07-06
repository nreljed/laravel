<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->boolean('visible')->default(true)->after('is_active');
        });

        DB::table('product_variations')
            ->whereNotNull('active_price')
            ->update(['visible' => true]);

        DB::table('product_variations')
            ->whereNull('active_price')
            ->update(['visible' => false]);
    }

    public function down(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn('visible');
        });
    }
};
