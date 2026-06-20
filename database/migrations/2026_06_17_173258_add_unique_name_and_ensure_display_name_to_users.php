<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('display_name')->orWhere('display_name', '')->update([
            'display_name' => DB::raw('name'),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('name', 60)->change();
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->string('name', 255)->change();
        });
    }
};
