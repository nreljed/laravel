<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'channel']);

            $table->string('type')->default('')->after('user_id');

            $table->unique(['user_id', 'type', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'type', 'channel']);

            $table->dropColumn('type');

            $table->unique(['user_id', 'channel']);
        });
    }
};
