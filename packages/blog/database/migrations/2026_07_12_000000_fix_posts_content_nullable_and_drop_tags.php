<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'content')) {
                $table->text('content')->nullable()->change();
            }
            if (Schema::hasColumn('posts', 'tags')) {
                $table->dropColumn('tags');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->text('content')->nullable(false)->change();
            $table->json('tags')->nullable();
        });
    }
};
