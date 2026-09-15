<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('back_in_stock_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained('product_variations')->cascadeOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['email', 'product_id', 'product_variation_id'], 'back_in_stock_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('back_in_stock_subscriptions');
    }
};
