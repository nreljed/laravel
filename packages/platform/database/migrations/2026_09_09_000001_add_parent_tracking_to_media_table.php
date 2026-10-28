<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('parent_type')->nullable()->after('folder');
            $table->unsignedBigInteger('parent_id')->nullable()->after('parent_type');
            $table->index(['parent_type', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['parent_type', 'parent_id']);
            $table->dropColumn(['parent_type', 'parent_id']);
        });
    }
};
