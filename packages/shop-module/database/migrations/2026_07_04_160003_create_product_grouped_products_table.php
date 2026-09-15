<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_grouped_products', function (Blueprint $table) {
            $table->foreignId('group_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->integer('sort_order')->default(0);
            $table->primary(['group_product_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_grouped_products');
    }
};
