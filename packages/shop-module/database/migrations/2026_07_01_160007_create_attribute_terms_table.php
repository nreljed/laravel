<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'slug']);
        });

        Schema::create('product_attribute_term', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_term_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'attribute_term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_term');
        Schema::dropIfExists('attribute_terms');
    }
};
