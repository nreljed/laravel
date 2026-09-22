<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PR 2 / Decision 1 — per-currency price ledger.
     *
     * One row per (product OR variation, currency, min_quantity). Legacy
     * `products.price` / `product_variations.price` columns remain the
     * default-currency source for the backfill; rows are mirrored here so the
     * storefront can resolve prices server-side per chosen currency instead of
     * converting client-side by exchange rate.
     */
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained()->cascadeOnDelete();
            // Product-level rows (variation NULL) and variation-level rows
            // must not collide: stored generated column normalizes NULL to 0.
            $table->unsignedBigInteger('price_scope')->storedAs('COALESCE(product_variation_id, 0)');
            $table->string('currency', 3)->index();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('compare_price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->timestamps();

            $table->unique(['product_id', 'price_scope', 'currency', 'min_quantity'], 'product_prices_lookup_unique');
        });

        $defaultCode = $this->defaultCurrencyCode();
        $now = now();

        $productRows = DB::table('products')
            ->select('id', 'price', 'compare_price', 'sale_price')
            ->get();

        foreach ($productRows as $product) {
            $rows = [[
                'product_id' => $product->id,
                'product_variation_id' => null,
                'currency' => $defaultCode,
                'price' => $product->price,
                'compare_price' => $product->compare_price,
                'sale_price' => $product->sale_price,
                'min_quantity' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]];

            $variations = DB::table('product_variations')
                ->where('product_id', $product->id)
                ->select('id', 'price', 'compare_price', 'sale_price')
                ->get();

            foreach ($variations as $variation) {
                $rows[] = [
                    'product_id' => $product->id,
                    'product_variation_id' => $variation->id,
                    'currency' => $defaultCode,
                    'price' => $variation->price,
                    'compare_price' => $variation->compare_price,
                    'sale_price' => $variation->sale_price,
                    'min_quantity' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('product_prices')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }

    protected function defaultCurrencyCode(): string
    {
        $code = DB::table('currencies')
            ->where('is_default', true)
            ->value('code')
            ?? DB::table('currencies')->where('is_active', true)->orderBy('code')->value('code');

        return strtoupper((string) ($code ?: 'EUR'));
    }
};