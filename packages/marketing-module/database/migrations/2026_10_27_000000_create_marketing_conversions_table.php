<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_link_id')
                ->nullable()
                ->constrained('marketing_links')
                ->nullOnDelete();
            $table->foreignId('marketing_link_click_id')
                ->nullable()
                ->constrained('marketing_link_clicks')
                ->nullOnDelete();

            // The shop's own event name, for example "order.paid".
            $table->string('source', 64)->default('shop');
            $table->string('event_type', 64)->default('conversion');

            // The shop's immutable reference, such as an order id.
            $table->string('external_reference', 191)->nullable();

            // A caller-supplied idempotency key. Replaying the same key
            // returns the original conversion instead of creating a duplicate.
            $table->string('idempotency_key', 191)->nullable()->unique('marketing_conversions_idempotency_key_unique');

            // Reported value only. Revenue remains owned by the shop.
            $table->decimal('value', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();

            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['marketing_link_id', 'occurred_at']);
            $table->index(['source', 'external_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_conversions');
    }
};
