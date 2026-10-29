<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record the outcome of stored-key connection tests on each provider entry.
 *
 * The Account → Integrations UI derives its Connected/Active/Failed status
 * pill from these two columns, and "Test connection" persists the result of
 * every run so a broken key is visible without re-testing by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->timestamp('last_tested_at')->nullable()->after('is_active');
            $table->boolean('last_test_ok')->nullable()->after('last_tested_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table) {
            $table->dropColumn(['last_tested_at', 'last_test_ok']);
        });
    }
};
