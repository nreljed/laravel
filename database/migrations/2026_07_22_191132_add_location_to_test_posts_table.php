<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_posts', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('metadata');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->json('address')->nullable()->after('longitude');
            $table->unsignedSmallInteger('map_zoom')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('test_posts', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'address', 'map_zoom']);
        });
    }
};
