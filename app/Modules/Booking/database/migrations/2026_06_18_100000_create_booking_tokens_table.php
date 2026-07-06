<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64);
            $table->string('guest_email');
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index('token_hash');
            $table->index(['booking_id', 'token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_tokens');
    }
};
