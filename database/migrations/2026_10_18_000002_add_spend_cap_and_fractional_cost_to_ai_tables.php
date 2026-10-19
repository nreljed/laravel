<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the token ceiling with a USD spend ceiling and make cost storage
 * precise enough to enforce it.
 *
 * - `ai_usage_settings.cap_cents` (default $5/day) is the new spend cap.
 *   The legacy `cap_tokens` column is kept — never dropped on consumers
 *   mid-upgrade — but the application ignores it from here on.
 * - `ai_usage_logs.cost_cents` widens from integer to fractional cents:
 *   a single sub-cent event (small Gemini/Mistral calls) rounded to 0 would
 *   make a day of cheap traffic invisible to the spend cap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_settings', function (Blueprint $table) {
            $table->integer('cap_cents')->default(500)->after('cap_requests');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->decimal('cost_cents', 14, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->integer('cost_cents')->nullable()->change();
        });

        Schema::table('ai_usage_settings', function (Blueprint $table) {
            $table->dropColumn('cap_cents');
        });
    }
};
