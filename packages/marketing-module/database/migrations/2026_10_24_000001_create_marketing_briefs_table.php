<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_plan_id')->nullable()->constrained('marketing_plans')->nullOnDelete();
            $table->string('title', 191);
            $table->string('objective', 32)->nullable();
            $table->text('audience')->nullable();
            $table->text('key_message')->nullable();
            $table->json('channels')->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamp('due_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_briefs');
    }
};
