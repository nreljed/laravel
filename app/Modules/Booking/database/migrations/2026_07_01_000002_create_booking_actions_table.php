<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->string('action_type', 50);
            $table->string('actor_type', 20)->default('system');
            $table->unsignedBigInteger('actor_id')->default(0);
            $table->string('actor_name', 255)->default('');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('notes')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('booking_id');
            $table->index('action_type');
            $table->index('actor_type');
            $table->index('created_at');

            $table->foreign('booking_id')
                ->references('id')
                ->on('bookings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_actions');
    }
};
