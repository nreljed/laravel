<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('product_variation_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variations')
                ->nullOnDelete();

            $table->unique(['cart_id', 'product_id', 'product_variation_id', 'variant_label'], 'cart_item_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_item_unique');
            $table->dropForeign(['product_variation_id']);
            $table->dropColumn('product_variation_id');
        });
    }
};
