<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deploy_releases', function (Blueprint $table) {
            $table->unsignedBigInteger('rolled_back_by')->nullable()->after('deployed_at');
            $table->timestamp('rolled_back_at')->nullable()->after('rolled_back_by');
        });
    }

    public function down(): void
    {
        Schema::table('deploy_releases', function (Blueprint $table) {
            $table->dropColumn(['rolled_back_by', 'rolled_back_at']);
        });
    }
};
