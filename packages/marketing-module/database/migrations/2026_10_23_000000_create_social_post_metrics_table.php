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
        Schema::create('social_post_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_target_id')->constrained()->cascadeOnDelete();

            // The day the snapshot was taken in the application timezone.
            $table->date('metric_date');
            $table->timestamp('captured_at');

            $table->unsignedBigInteger('impressions')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments')->nullable();
            $table->unsignedBigInteger('shares')->nullable();
            $table->unsignedBigInteger('saves')->nullable();
            $table->unsignedBigInteger('clicks')->nullable();
            $table->unsignedBigInteger('video_views')->nullable();
            $table->unsignedBigInteger('replies')->nullable();
            $table->unsignedBigInteger('reposts')->nullable();
            $table->unsignedBigInteger('quotes')->nullable();
            $table->unsignedBigInteger('bookmarks')->nullable();
            $table->unsignedBigInteger('profile_visits')->nullable();

            // Normalised counters that do not have a first-class column yet.
            $table->json('custom')->nullable();

            // The provider payload after credential-like keys are stripped.
            $table->json('raw')->nullable();
            $table->string('source', 32)->nullable();

            $table->timestamps();

            $table->unique(['social_post_target_id', 'metric_date']);
            $table->index('metric_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_post_metrics');
    }
};
