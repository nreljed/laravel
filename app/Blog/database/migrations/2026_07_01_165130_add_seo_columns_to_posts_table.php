<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('meta_title', 70)->nullable()->after('metadata');
            $table->string('meta_description', 160)->nullable()->after('meta_title');
            $table->string('og_image')->nullable()->after('meta_description');
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'og_image']);
            $table->string('category')->nullable();
        });
    }
};
