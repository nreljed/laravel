<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('social_post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('social_post_target_id')->nullable()->constrained()->cascadeOnDelete();

            $table->text('destination_url');
            $table->string('utm_source', 255)->nullable();
            $table->string('utm_medium', 255)->nullable();
            $table->string('utm_campaign', 255)->nullable();
            $table->string('utm_content', 255)->nullable();
            $table->string('utm_term', 255)->nullable();

            $table->string('short_code', 16)->unique();
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->timestamp('last_clicked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('social_post_target_id');
            $table->index('social_post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_links');
    }
};
