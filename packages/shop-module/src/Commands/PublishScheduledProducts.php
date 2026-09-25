<?php

namespace Nreljed\ShopModule\Commands;

use Illuminate\Console\Command;
use Nreljed\ShopModule\Models\Product;

class PublishScheduledProducts extends Command
{
    protected $signature = 'shop:publish-scheduled';

    protected $description = 'Promote scheduled products to published and auto-unpublish expired ones.';

    public function handle(): int
    {
        $now = now();

        // Scheduled (future) products whose publish_date has arrived become published.
        $published = Product::query()
            ->where('status', 'future')
            ->whereNotNull('publish_date')
            ->where('publish_date', '<=', $now)
            ->get();

        $publishedCount = 0;
        foreach ($published as $product) {
            $product->status = 'published';
            $product->publish_date = null;
            $product->unpublish_at = $product->unpublish_at?->gt($now) ? $product->unpublish_at : null;
            $product->saveQuietly();
            $publishedCount++;
        }

        // Published products whose unpublish_at has passed are auto-hidden.
        $unpublished = Product::query()
            ->where('status', 'published')
            ->whereNotNull('unpublish_at')
            ->where('unpublish_at', '<=', $now)
            ->get();

        $unpublishedCount = 0;
        foreach ($unpublished as $product) {
            $product->status = 'archived';
            $product->saveQuietly();
            $unpublishedCount++;
        }

        $this->info("Published {$publishedCount} scheduled product(s); auto-unpublished {$unpublishedCount} product(s).");

        return Command::SUCCESS;
    }
}
