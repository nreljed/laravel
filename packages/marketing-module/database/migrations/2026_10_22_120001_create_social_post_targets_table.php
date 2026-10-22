<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('social_post_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();

            // pending, publishing, published, failed, skipped.
            $table->string('status', 32)->default('pending');

            // Attempts already made. The unique key below means a target is
            // one delivery no matter how many times it is retried.
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();

            // What the platform returned when the delivery succeeded.
            $table->string('external_post_id', 191)->nullable();
            $table->string('external_url', 2048)->nullable();

            // Why the last attempt failed. Credentials never land here.
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['social_post_id', 'social_account_id']);
            $table->index(['status', 'next_attempt_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_post_targets');
    }
};
