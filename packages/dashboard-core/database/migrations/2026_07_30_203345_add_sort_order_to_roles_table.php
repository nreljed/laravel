<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedTinyInteger('sort_order')->nullable()->after('description');
        });

        DB::table('roles')->where('slug', 'admin')->update(['sort_order' => 0]);
        DB::table('roles')->where('slug', 'editor')->update(['sort_order' => 1]);
        DB::table('roles')->where('slug', 'author')->update(['sort_order' => 2]);
        DB::table('roles')->where('slug', 'user')->update(['sort_order' => 3]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
