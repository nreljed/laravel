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
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->text('description')->nullable();

            // contact_created, post_published.
            $table->string('trigger', 32);

            // add_tag, remove_tag, set_status, webhook.
            $table->string('action', 32);

            // Only run when the contact already carries this tag.
            $table->foreignId('condition_tag_id')
                ->nullable()
                ->constrained('tags')
                ->nullOnDelete();

            // The tag added/removed by the action.
            $table->foreignId('tag_id')
                ->nullable()
                ->constrained('tags')
                ->nullOnDelete();

            // The contact status written by set_status.
            $table->string('contact_status', 32)->nullable();

            $table->string('webhook_url', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('run_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['trigger', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
