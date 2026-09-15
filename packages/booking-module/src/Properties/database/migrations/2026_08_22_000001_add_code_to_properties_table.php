<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('name');
            $table->unique('code');
        });

        DB::table('properties')->whereNull('code')->orderBy('id')->chunkById(100, function ($properties) {
            foreach ($properties as $property) {
                DB::table('properties')
                    ->where('id', $property->id)
                    ->update(['code' => 'PR-'.str_pad((string) $property->id, 4, '0', STR_PAD_LEFT)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
