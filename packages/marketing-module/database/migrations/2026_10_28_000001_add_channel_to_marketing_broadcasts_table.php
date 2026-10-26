<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_broadcasts', function (Blueprint $table) {
            $table->string('channel', 16)->default('email')->after('name');
            $table->text('body_text')->nullable()->after('body_html');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_broadcasts', function (Blueprint $table) {
            $table->dropColumn(['channel', 'body_text']);
        });
    }
};
