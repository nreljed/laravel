<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->text('bio')->nullable()->after('display_name');
            $table->string('avatar')->nullable()->after('bio');
            $table->json('social_links')->nullable()->after('avatar');
            $table->string('phone')->nullable()->after('social_links');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'bio', 'avatar', 'social_links', 'phone']);
        });
    }
};
