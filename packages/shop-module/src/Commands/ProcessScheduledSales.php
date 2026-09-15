<?php

namespace Nreljed\ShopModule\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductVariation;

class ProcessScheduledSales extends Command
{
    protected $signature = 'shop:process-scheduled-sales';

    protected $description = 'Evaluate sale_from/sale_to on variations and update active_price. Syncs parent product price ranges.';

    public function handle(): int
    {
        $now = now();
        $updated = 0;

        // Variations where sale has started — set active_price = sale_price
        $started = ProductVariation::query()
            ->whereNotNull('sale_price')
            ->whereNotNull('sale_from')
            ->where('sale_from', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('sale_to')
                    ->orWhere('sale_to', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('active_price')
                    ->orWhereColumn('active_price', '!=', 'sale_price');
            })
            ->get();

        foreach ($started as $variation) {
            $variation->active_price = $variation->sale_price;
            $variation->saveQuietly();
            $updated++;
        }

        // Variations where sale has ended — set active_price = regular_price
        $ended = ProductVariation::query()
            ->whereNotNull('sale_price')
            ->whereNotNull('sale_to')
            ->where('sale_to', '<', $now)
            ->whereNotNull('active_price')
            ->whereColumn('active_price', '!=', 'price')
            ->get();

        foreach ($ended as $variation) {
            $variation->active_price = $variation->price;
            $variation->saveQuietly();
            $updated++;
        }

        // Variations with no date bounds and sale_price set (always on sale)
        $alwaysSale = ProductVariation::query()
            ->whereNotNull('sale_price')
            ->whereNull('sale_from')
            ->whereNull('sale_to')
            ->where(function ($q) {
                $q->whereNull('active_price')
                    ->orWhereColumn('active_price', '!=', 'sale_price');
            })
            ->get();

        foreach ($alwaysSale as $variation) {
            $variation->active_price = $variation->sale_price;
            $variation->saveQuietly();
            $updated++;
        }

        // Sync parent price ranges for all affected products
        $affectedProductIds = collect()
            ->merge($started->pluck('product_id'))
            ->merge($ended->pluck('product_id'))
            ->merge($alwaysSale->pluck('product_id'))
            ->unique()
            ->values();

        foreach ($affectedProductIds as $productId) {
            DB::transaction(function () use ($productId) {
                $product = Product::find($productId);
                if ($product) {
                    $product->syncPriceRange();
                }
            });
        }

        $this->info("Processed {$updated} variation(s). Synced ".$affectedProductIds->count().' product(s).');

        // G8: also clear price_hash for affected products
        foreach ($affectedProductIds as $productId) {
            DB::table('products')
                ->where('id', $productId)
                ->update(['price_hash' => null]);
        }

        return Command::SUCCESS;
    }
}
