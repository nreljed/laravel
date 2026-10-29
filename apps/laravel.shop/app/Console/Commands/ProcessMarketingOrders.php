<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Nreljed\MarketingModule\Enums\ContactStatus;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Services\WorkflowEngine;
use Nreljed\ShopModule\Models\Order;

class ProcessMarketingOrders extends Command
{
    protected $signature = 'marketing:process-orders {--dry-run : Show what would be processed without writing}';

    protected $description = 'Sync recent shop orders into marketing: create/update contacts and trigger order-placed workflows.';

    public function handle(WorkflowEngine $engine): int
    {
        $lastId = (int) Cache::get('marketing.last_processed_order_id', 0);

        $orders = Order::query()
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No new orders to process.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $processed = 0;

        foreach ($orders as $order) {
            $email = $order->customer_email;

            if (! $email) {
                $lastId = max($lastId, $order->id);

                continue;
            }

            $contact = Contact::where('email', $email)->first();
            $orderTotal = (float) $order->total;

            if (! $contact) {
                $contact = Contact::create([
                    'email' => $email,
                    'user_id' => $order->user_id,
                    'first_name' => $order->customer_name,
                    'phone' => $order->customer_phone,
                    'status' => ContactStatus::Active,
                    'source' => 'shop',
                    'total_spent' => $orderTotal,
                    'order_count' => 1,
                    'last_order_at' => $order->created_at,
                ]);
            } else {
                $contact->fill([
                    'user_id' => $contact->user_id ?: $order->user_id,
                    'first_name' => $contact->first_name ?: $order->customer_name,
                    'phone' => $contact->phone ?: $order->customer_phone,
                    'source' => 'shop',
                    'total_spent' => (float) $contact->total_spent + $orderTotal,
                    'order_count' => (int) $contact->order_count + 1,
                    'last_order_at' => $order->created_at,
                ])->save();
            }

            if ($dryRun) {
                $this->line("[dry-run] Order #{$order->id} ({$email}) -> contact #{$contact->id}");

                continue;
            }

            $engine->orderPlaced($contact, [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total' => (float) $order->total,
                'status' => $order->status?->value ?? (string) $order->status,
                'currency' => $order->currency,
                'item_count' => $order->items()->count(),
            ]);

            $processed++;
        }

        if (! $dryRun) {
            Cache::put('marketing.last_processed_order_id', $orders->last()->id, now()->addDays(30));
            $this->info("Processed {$processed} order(s). Last order ID: {$orders->last()->id}.");
        }

        return self::SUCCESS;
    }
}
