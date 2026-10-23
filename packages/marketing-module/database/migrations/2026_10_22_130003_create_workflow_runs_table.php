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
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();

            // The event that matched - contact_created or post_published.
            $table->string('event', 32);

            // The contact or post the workflow acted on.
            $table->string('subject_type', 191)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // success, failed, skipped.
            $table->string('status', 16)->default('success');
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('ran_at');
            $table->timestamps();

            $table->index(['workflow_id', 'ran_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
    }
};
