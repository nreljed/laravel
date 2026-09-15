<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('is_automatic')->default(false)->after('per_user_limit');
            $table->string('auto_discount_label')->nullable()->after('is_automatic');
            $table->index('is_automatic');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['is_automatic']);
            $table->dropColumn(['is_automatic', 'auto_discount_label']);
        });
    }
};
