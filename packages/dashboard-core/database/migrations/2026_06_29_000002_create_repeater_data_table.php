<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repeater_data', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('field_key');
            $table->string('module')->nullable();
            $table->json('data');
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('field_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repeater_data');
    }
};
