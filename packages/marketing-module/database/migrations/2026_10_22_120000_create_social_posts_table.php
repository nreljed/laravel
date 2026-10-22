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
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The normalised PostContent an adapter receives. Media is a list
            // of {url, path, mime, title}; hashtags are stored without the
            // leading `#` so the same list can be rendered either way.
            $table->text('body')->nullable();
            $table->json('media')->nullable();
            $table->string('link', 2048)->nullable();
            $table->json('hashtags')->nullable();

            // draft, scheduled, publishing, published, partial, failed,
            // cancelled.
            $table->string('status', 32)->default('draft');

            // When the scheduler should pick the post up. Null means the post
            // is a draft and waits for an explicit publish.
            $table->timestamp('scheduled_at')->nullable();

            // The wall-clock timezone the author scheduled in; `scheduled_at`
            // itself is stored in the app timezone.
            $table->string('timezone', 64)->nullable();

            // With publishing.approval_required on (the default), a scheduled
            // post waits for a human to set approved_at before it goes out.
            $table->boolean('requires_approval')->default(true);
            $table->timestamp('approved_at')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
