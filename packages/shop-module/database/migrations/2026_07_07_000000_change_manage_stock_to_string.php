<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->string('manage_stock', 10)->default('true')->change();
        });

        DB::table('product_variations')
            ->whereIn('manage_stock', [1, '1', true])
            ->update(['manage_stock' => 'true']);

        DB::table('product_variations')
            ->whereIn('manage_stock', [0, '0', false, null])
            ->update(['manage_stock' => 'false']);
    }

    public function down(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->boolean('manage_stock')->default(true)->change();
        });

        DB::table('product_variations')->where('manage_stock', 'true')->update(['manage_stock' => true]);
        DB::table('product_variations')->where('manage_stock', 'false')->update(['manage_stock' => false]);
        DB::table('product_variations')->where('manage_stock', 'parent')->update(['manage_stock' => true]);
    }
};
