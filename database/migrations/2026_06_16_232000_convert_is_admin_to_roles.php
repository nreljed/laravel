<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Full access to everything',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')
            ->where('is_admin', true)
            ->whereNotExists(function ($query) use ($adminRoleId) {
                $query->select(DB::raw(1))
                    ->from('role_user')
                    ->whereColumn('role_user.user_id', 'users.id')
                    ->where('role_user.role_id', $adminRoleId);
            })
            ->chunkById(100, function ($users) use ($adminRoleId) {
                foreach ($users as $user) {
                    DB::table('role_user')->insert([
                        'role_id' => $adminRoleId,
                        'user_id' => $user->id,
                    ]);
                }
            });

        if (Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_admin');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_admin')->default(false)->after('email');
            });
        }

        DB::table('role_user')->whereIn(
            'role_id',
            DB::table('roles')->where('slug', 'admin')->pluck('id')
        )->delete();

        DB::table('roles')->where('slug', 'admin')->delete();
    }
};
