<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_field_id')->constrained('product_fields')->cascadeOnDelete();
            $table->json('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'product_field_id']);
            $table->index('product_field_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_field_values');
    }
};
