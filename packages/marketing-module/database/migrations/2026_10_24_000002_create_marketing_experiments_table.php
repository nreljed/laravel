<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_brief_id')->nullable()->constrained('marketing_briefs')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->string('name', 191);
            $table->text('hypothesis')->nullable();
            $table->string('success_metric', 64)->default('engagement_rate');
            $table->string('status', 32)->default('draft');
            $table->json('variants')->nullable();
            $table->string('winner', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_experiments');
    }
};
