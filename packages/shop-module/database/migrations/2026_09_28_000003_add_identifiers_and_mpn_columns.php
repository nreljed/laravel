<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('mpn')->nullable()->after('global_unique_id');
        });

        // `barcode` already exists on `product_variations`; the variation was
        // only missing the two identifiers a product carries beyond its
        // barcode - the GTIN/UPC/EAN/ISBN `global_unique_id` and the
        // manufacturer part number `mpn`.
        Schema::table('product_variations', function (Blueprint $table) {
            $table->string('global_unique_id')->nullable()->after('barcode');
            $table->string('mpn')->nullable()->after('global_unique_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('mpn');
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn(['global_unique_id', 'mpn']);
        });
    }
};