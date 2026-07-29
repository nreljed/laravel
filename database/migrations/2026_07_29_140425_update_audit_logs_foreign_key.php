<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->recreateTableForSqlite('SET NULL');
        } else {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table('audit_logs', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->change();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->recreateTableForSqlite('CASCADE');
        } else {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });

            Schema::table('audit_logs', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable(false)->change();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    private function recreateTableForSqlite(string $onDelete): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        $tmpTable = 'audit_logs_tmp_'.time();

        DB::statement("
            CREATE TABLE {$tmpTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                action VARCHAR(50) NOT NULL,
                user_id INTEGER NULL,
                ip VARCHAR(45) NOT NULL DEFAULT '',
                user_agent VARCHAR(500) NOT NULL DEFAULT '',
                context TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE {$onDelete}
            )
        ");

        DB::statement("INSERT INTO {$tmpTable} SELECT * FROM audit_logs");
        DB::statement('DROP TABLE audit_logs');
        DB::statement("ALTER TABLE {$tmpTable} RENAME TO audit_logs");

        DB::statement('CREATE INDEX audit_logs_action_index ON audit_logs(action)');
        DB::statement('CREATE INDEX audit_logs_user_id_index ON audit_logs(user_id)');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
