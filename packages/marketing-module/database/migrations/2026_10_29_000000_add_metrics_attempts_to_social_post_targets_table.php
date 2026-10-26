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
            // Consecutive failed metric-sync attempts. Reset on success and on
            // an explicit dead-letter replay.
            $table->unsignedInteger('metrics_attempts')->default(0)->after('metrics_status');
            $table->index(['metrics_status', 'metrics_attempts'], 'social_post_targets_metrics_dead_letter_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_post_targets', function (Blueprint $table) {
            $table->dropIndex('social_post_targets_metrics_dead_letter_index');
            $table->dropColumn('metrics_attempts');
        });
    }
};
