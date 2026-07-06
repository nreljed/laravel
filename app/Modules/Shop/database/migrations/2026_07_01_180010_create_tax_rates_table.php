<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 5, 2); // e.g. 20.00 for 20%
            $table->string('type')->default('percentage'); // percentage, fixed
            $table->string('country', 2)->nullable();
            $table->string('state')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
