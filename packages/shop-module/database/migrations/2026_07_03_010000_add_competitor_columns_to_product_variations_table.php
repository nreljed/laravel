<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->decimal('compare_price', 10, 2)->nullable()->after('price');
            $table->decimal('sale_price', 10, 2)->nullable()->after('compare_price');
            $table->timestamp('sale_from')->nullable()->after('sale_price');
            $table->timestamp('sale_to')->nullable()->after('sale_from');
            $table->string('barcode', 100)->nullable()->after('sku');
            $table->decimal('length', 10, 2)->nullable()->after('weight');
            $table->decimal('width', 10, 2)->nullable()->after('length');
            $table->decimal('height', 10, 2)->nullable()->after('width');
            $table->text('description')->nullable()->after('image_url');
            $table->boolean('manage_stock')->default(true)->after('description');
            $table->boolean('allow_backorders')->default(false)->after('manage_stock');
        });
    }

    public function down(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn([
                'compare_price', 'sale_price', 'sale_from', 'sale_to',
                'barcode', 'length', 'width', 'height', 'description',
                'manage_stock', 'allow_backorders',
            ]);
        });
    }
};
