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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone', 64)->nullable();

            // lead, active, inactive, blocked.
            $table->string('status', 32)->default('lead');

            // Where the contact came from: a form submission, an import, a
            // manual entry, a social account.
            $table->string('source', 64)->nullable();

            $table->json('metadata')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();

            $table->unique('email');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
