<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id');
            $table->decimal('total_spent', 12, 2)->default(0)->after('last_contacted_at');
            $table->unsignedInteger('order_count')->default(0)->after('total_spent');
            $table->timestamp('last_order_at')->nullable()->after('order_count');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'total_spent', 'order_count', 'last_order_at']);
        });
    }
};