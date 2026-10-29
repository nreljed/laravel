<?php

namespace App\Observers;

use Illuminate\Support\Facades\Cache;
use Nreljed\MarketingModule\Enums\ContactStatus;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Services\WorkflowEngine;
use Nreljed\ShopModule\Models\Order;

class OrderMarketingObserver
{
    public function created(Order $order): void
    {
        $email = $order->customer_email;

        if (! $email) {
            return;
        }

        // Ensure a marketing contact exists for this buyer. Purchase totals are
        // accumulated by the scheduled `marketing:process-orders` command so the
        // two never double-count.
        $contact = Contact::firstOrCreate(
            ['email' => $email],
            [
                'user_id' => $order->user_id,
                'first_name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'status' => ContactStatus::Active,
                'source' => 'shop',
            ],
        );

        try {
            app(WorkflowEngine::class)->orderPlaced($contact, [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total' => (float) $order->total,
                'status' => $order->status?->value ?? (string) $order->status,
                'currency' => $order->currency,
                'item_count' => $order->items()->count(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        // Mark this order as handled so the scheduled backfill skips it.
        Cache::put('marketing.last_processed_order_id', max(
            (int) Cache::get('marketing.last_processed_order_id', 0),
            $order->id,
        ), now()->addDays(30));
    }
}
