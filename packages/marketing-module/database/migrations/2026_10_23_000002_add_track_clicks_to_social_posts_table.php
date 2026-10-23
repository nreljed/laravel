<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table): void {
            $table->boolean('track_clicks')->default(false)->after('link');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table): void {
            $table->dropColumn('track_clicks');
        });
    }
};
