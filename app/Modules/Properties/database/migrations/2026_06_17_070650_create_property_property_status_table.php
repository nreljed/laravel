<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_property_status', function (Blueprint $table) {
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_status_id')->constrained('property_statuses')->cascadeOnDelete();
            $table->primary(['property_id', 'property_status_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_property_status');
    }
};
