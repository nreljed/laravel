<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_link_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_link_id')->constrained()->cascadeOnDelete();
            $table->timestamp('clicked_at');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->text('referer')->nullable();
            $table->string('country', 64)->nullable();
            $table->timestamps();

            $table->index('clicked_at');
            $table->index(['marketing_link_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_link_clicks');
    }
};
