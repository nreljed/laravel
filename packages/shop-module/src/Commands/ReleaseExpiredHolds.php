<?php

namespace Nreljed\ShopModule\Commands;

use Illuminate\Console\Command;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Services\InventoryService;

class ReleaseExpiredHolds extends Command
{
    protected $signature = 'shop:release-expired-holds';

    protected $description = 'Release stock held by expired unpaid orders';

    public function handle(InventoryService $inventoryService): int
    {
        if (! config('shop.hold_stock.enabled', true)) {
            $this->info('Hold stock is disabled. Skipping.');

            return self::SUCCESS;
        }

        $defaultMinutes = (int) config('shop.hold_stock.default_minutes', 60);

        $candidates = Order::with('items.product')
            ->where('status', 'pending')
            ->where('payment_status', 'pending')
            ->get()
            ->filter(function ($order) use ($defaultMinutes) {
                $minutes = $order->items->reduce(function ($carry, $item) use ($defaultMinutes) {
                    $productMinutes = $item->product?->hold_stock_minutes ?? $defaultMinutes;

                    return min($carry, (int) $productMinutes);
                }, PHP_INT_MAX);

                return $order->created_at->lte(now()->subMinutes($minutes));
            });

        if ($candidates->isEmpty()) {
            $this->info('No expired holds found.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($candidates as $order) {
            $order->loadMissing('items.variation');

            $inventoryService->recordRefundForOrder($order);

            $productMinutes = $order->items->reduce(function ($carry, $item) use ($defaultMinutes) {
                $productMinutes = $item->product?->hold_stock_minutes ?? $defaultMinutes;

                return min($carry, (int) $productMinutes);
            }, PHP_INT_MAX);

            $order->update([
                'status' => 'cancelled',
                'notes' => 'Auto-cancelled — hold stock expired after '.$productMinutes.' minutes.',
            ]);

            $count++;
        }

        $this->info("Released stock for {$count} expired order(s).");

        return self::SUCCESS;
    }
}
