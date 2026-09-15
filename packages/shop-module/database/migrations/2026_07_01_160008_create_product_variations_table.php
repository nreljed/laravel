<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->decimal('weight', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('image_url')->nullable();
            $table->json('attributes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('product_id');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
