<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('catalog_visibility')->default('visible')->after('status');
            $table->string('global_unique_id')->nullable()->after('barcode');
            $table->boolean('sold_individually')->default(false)->after('is_featured');
            $table->integer('min_quantity')->nullable()->after('sold_individually');
            $table->integer('max_quantity')->nullable()->after('min_quantity');
            $table->integer('menu_order')->default(0)->after('max_quantity');
            $table->boolean('virtual')->default(false)->after('type');
            $table->boolean('downloadable')->default(false)->after('virtual');
            $table->string('external_url')->nullable()->after('downloadable');
            $table->string('button_text')->nullable()->after('external_url');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'catalog_visibility',
                'global_unique_id',
                'sold_individually',
                'min_quantity',
                'max_quantity',
                'menu_order',
                'virtual',
                'downloadable',
                'external_url',
                'button_text',
            ]);
        });
    }
};
