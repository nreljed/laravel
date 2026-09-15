<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cover pivot table queries filtered by user_id (query + permission cache loads).
        Schema::table('role_user', function (Blueprint $table) {
            $table->index('user_id', 'role_user_user_id_index');
        });

        Schema::table('permission_user', function (Blueprint $table) {
            $table->index('user_id', 'permission_user_user_id_index');
        });

        // Audit log pruning + listing filter by created_at.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at', 'audit_logs_created_at_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('role_user', 'role_user_user_id_index')) {
            Schema::table('role_user', function (Blueprint $table) {
                $table->dropIndex('role_user_user_id_index');
            });
        }

        if (Schema::hasIndex('permission_user', 'permission_user_user_id_index')) {
            Schema::table('permission_user', function (Blueprint $table) {
                $table->dropIndex('permission_user_user_id_index');
            });
        }

        if (Schema::hasIndex('audit_logs', 'audit_logs_created_at_index')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex('audit_logs_created_at_index');
            });
        }
    }
};
