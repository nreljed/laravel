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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->text('description')->nullable();

            // awareness, engagement, leads, sales.
            $table->string('objective', 32)->default('awareness');

            // draft, active, completed, archived.
            $table->string('status', 32)->default('draft');

            // The window the campaign is meant to run in. Posts can still be
            // scheduled outside it, but the campaign row reports the mismatch.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
