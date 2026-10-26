<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('marketing_broadcast_recipients');
        Schema::dropIfExists('marketing_broadcasts');

        Schema::create('marketing_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('subject', 191)->nullable();
            $table->longText('body_html')->nullable();
            $table->string('status', 32)->default('draft');
            $table->foreignId('segment_id')->nullable()->constrained('segments')->nullOnDelete();
            $table->foreignId('tag_id')->nullable()->constrained('tags')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('marketing_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_broadcast_id')->constrained('marketing_broadcasts')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['marketing_broadcast_id', 'contact_id'], 'mbc_broadcast_contact_unique');
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_broadcast_recipients');
        Schema::dropIfExists('marketing_broadcasts');
    }
};

