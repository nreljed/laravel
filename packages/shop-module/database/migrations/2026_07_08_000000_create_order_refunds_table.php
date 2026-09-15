<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->json('items');
            $table->decimal('shipping_refund', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('total_refunded', 10, 2)->default(0)->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('total_refunded');
        });
        Schema::dropIfExists('order_refunds');
    }
};
