<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zone_method', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->decimal('override_price', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['shipping_zone_id', 'shipping_method_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_method');
    }
};
