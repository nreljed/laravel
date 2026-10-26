<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->json('steps')->nullable()->after('metadata');
        });

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->unsignedInteger('step_index')->nullable()->after('workflow_id');
            $table->index(['status', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropIndex(['status', 'ran_at']);
            $table->dropColumn('step_index');
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('steps');
        });
    }
};
