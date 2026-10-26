<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_insights', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 191)->unique();
            $table->unsignedSmallInteger('period_days');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('type', 32);
            $table->string('title', 191);
            $table->text('summary');
            $table->text('recommendation');
            $table->json('evidence')->nullable();
            $table->string('confidence', 16)->default('medium');
            $table->string('status', 32)->default('new');
            $table->string('generated_by', 32)->default('rules');
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_insights');
    }
};
