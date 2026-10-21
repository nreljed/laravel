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
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The platform key from the driver registry: meta, instagram,
            // linkedin, x, pinterest.
            $table->string('platform', 32);

            // The platform's own identifier for the account (a Meta Page id,
            // a LinkedIn organization id, ...). Not unique on its own: two
            // users may connect the same Page for two brands.
            $table->string('account_id', 128);
            $table->string('account_name');
            $table->string('username')->nullable();

            // Credentials are encrypted with the app key before they land here.
            // A refresh token is stored separately because a platform may
            // return one only on the first exchange and never again.
            $table->text('access_token_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->string('status', 32)->default('active');

            // Platform-specific settings that do not deserve a column of
            // their own (a Pinterest board, a Meta page category).
            $table->json('metadata')->nullable();

            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'platform', 'account_id']);
            $table->index(['platform', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
