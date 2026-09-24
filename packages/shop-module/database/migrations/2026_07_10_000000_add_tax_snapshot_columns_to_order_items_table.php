<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('tax_class_code')->nullable()->after('tax_total');
            $table->string('tax_zone_code')->nullable()->after('tax_class_code');
            $table->decimal('tax_rate_snapshot', 8, 6)->nullable()->after('tax_zone_code');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['tax_rate_snapshot', 'tax_zone_code', 'tax_class_code']);
        });
    }
};
