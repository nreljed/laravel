<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // G1: active_price on variations — persisted derived price (sale or regular)
        Schema::table('product_variations', function (Blueprint $table) {
            $table->decimal('active_price', 10, 2)->nullable()->after('sale_to');
            $table->index('active_price');
        });

        // Backfill active_price for existing variations
        $variations = DB::table('product_variations')->get();
        foreach ($variations as $v) {
            $hasSale = $v->sale_price && $v->sale_from && $v->sale_to;
            $now = now();
            if ($hasSale && $now->between($v->sale_from, $v->sale_to)) {
                $activePrice = $v->sale_price;
            } elseif ($v->sale_price && ! $v->sale_from && ! $v->sale_to) {
                $activePrice = $v->sale_price;
            } else {
                $activePrice = $v->price;
            }

            DB::table('product_variations')
                ->where('id', $v->id)
                ->update(['active_price' => $activePrice]);
        }

        // G2 + G8: price range cache + price_hash on products
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('min_variation_price', 10, 2)->nullable()->after('price');
            $table->decimal('max_variation_price', 10, 2)->nullable()->after('min_variation_price');
            $table->string('price_hash', 32)->nullable()->after('max_variation_price');
        });

        // Backfill price ranges for variable products
        $products = DB::table('products')->where('type', 'variable')->get();
        foreach ($products as $product) {
            $prices = DB::table('product_variations')
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->whereNotNull('active_price')
                ->pluck('active_price');

            if ($prices->isNotEmpty()) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'min_variation_price' => $prices->min(),
                        'max_variation_price' => $prices->max(),
                        'price_hash' => md5($prices->sort()->values()->implode(',')),
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropIndex(['active_price']);
            $table->dropColumn('active_price');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['min_variation_price', 'max_variation_price', 'price_hash']);
        });
    }
};
