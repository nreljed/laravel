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
        Schema::table('social_post_targets', function (Blueprint $table) {
            // pending, ok, unavailable, error.
            $table->string('metrics_status', 16)->default('pending');
            $table->timestamp('metrics_attempted_at')->nullable();
            $table->timestamp('metrics_synced_at')->nullable();
            $table->string('metrics_error', 500)->nullable();

            $table->index(['metrics_status', 'metrics_attempted_at'], 'social_post_targets_metrics_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_post_targets', function (Blueprint $table) {
            $table->dropIndex('social_post_targets_metrics_index');
            $table->dropColumn([
                'metrics_status',
                'metrics_attempted_at',
                'metrics_synced_at',
                'metrics_error',
            ]);
        });
    }
};
