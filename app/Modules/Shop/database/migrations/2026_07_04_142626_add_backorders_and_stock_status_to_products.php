<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Products table
        Schema::table('products', function (Blueprint $table) {
            $table->string('stock_status')->nullable()->after('stock_quantity');
            $table->string('backorders')->default('no')->after('allow_backorders');
        });

        DB::table('products')->where('allow_backorders', true)->update(['backorders' => 'yes']);
        DB::table('products')->where('allow_backorders', false)->update(['backorders' => 'no']);

        // Product variations table
        Schema::table('product_variations', function (Blueprint $table) {
            $table->string('stock_status')->nullable()->after('stock_quantity');
            $table->string('backorders')->default('no')->after('allow_backorders');
        });

        DB::table('product_variations')->where('allow_backorders', true)->update(['backorders' => 'yes']);
        DB::table('product_variations')->where('allow_backorders', false)->update(['backorders' => 'no']);

        // Auto-calculate stock_status for products with tracking enabled
        DB::table('products')
            ->where('track_stock', true)
            ->where('stock_quantity', '>', 0)
            ->update(['stock_status' => 'instock']);

        DB::table('products')
            ->where('track_stock', true)
            ->where('stock_quantity', '<=', 0)
            ->where('backorders', '!=', 'no')
            ->update(['stock_status' => 'onbackorder']);

        DB::table('products')
            ->where('track_stock', true)
            ->where('stock_quantity', '<=', 0)
            ->where('backorders', 'no')
            ->update(['stock_status' => 'outofstock']);

        // For products without tracking, set based on stock_quantity heuristic
        DB::table('products')
            ->where('track_stock', false)
            ->where('stock_quantity', '>', 0)
            ->update(['stock_status' => 'instock']);

        DB::table('products')
            ->where('track_stock', false)
            ->where(function ($q) {
                $q->where('stock_quantity', '<=', 0)->orWhereNull('stock_quantity');
            })
            ->update(['stock_status' => 'outofstock']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_status', 'backorders']);
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn(['stock_status', 'backorders']);
        });
    }
};
