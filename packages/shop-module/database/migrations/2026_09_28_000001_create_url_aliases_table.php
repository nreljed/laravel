<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('url_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->morphs('target');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('url_aliases');
    }
};