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
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title')->nullable()->after('data');
            $table->text('message')->nullable()->after('title');
            $table->string('link', 500)->nullable()->after('message');
            $table->string('severity', 20)->default('info')->after('link');
            $table->string('module', 50)->nullable()->after('severity');

            // Composite index for efficient unread count queries
            $table->index(['notifiable_id', 'notifiable_type', 'read_at', 'created_at'], 'notifications_unread_meta_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_unread_meta_index');
            $table->dropColumn(['title', 'message', 'link', 'severity', 'module']);
        });
    }
};
