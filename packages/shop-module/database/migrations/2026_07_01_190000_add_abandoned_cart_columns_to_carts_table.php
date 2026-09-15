<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('discount');
            $table->timestamp('abandoned_at')->nullable()->after('last_activity_at');
            $table->timestamp('recovered_at')->nullable()->after('abandoned_at');
            $table->timestamp('recovery_email_sent_at')->nullable()->after('recovered_at');
            $table->unsignedTinyInteger('recovery_email_count')->default(0)->after('recovery_email_sent_at');
            $table->foreignId('converted_to_order_id')->nullable()->constrained('orders')->nullOnDelete()->after('recovery_email_count');
            $table->index('last_activity_at');
            $table->index('abandoned_at');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign(['converted_to_order_id']);
            $table->dropColumn([
                'last_activity_at',
                'abandoned_at',
                'recovered_at',
                'recovery_email_sent_at',
                'recovery_email_count',
                'converted_to_order_id',
            ]);
        });
    }
};
