<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PR 4 / 0.4 — webhook endpoint auto-deactivation.
     *
     * Tracks consecutive delivery failures per webhook. When the count
     * reaches the failure ceiling the endpoint is auto-deactivated
     * (aligned with docs/workflows.md).
     */
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->unsignedInteger('consecutive_failures')->default(0)->after('retry_delay');
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropColumn('consecutive_failures');
        });
    }
};
