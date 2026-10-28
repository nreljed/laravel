<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 50);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45)->default('');
            $table->string('user_agent', 500)->default('');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('action');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
