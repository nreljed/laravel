<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Schema;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Services\WorkflowEngine;
use Nreljed\ShopModule\Events\CartAbandoned;

class MarketingCartAbandonedListener
{
    public function handle(CartAbandoned $event): void
    {
        if (! class_exists(Contact::class) || ! class_exists(WorkflowEngine::class)) {
            return;
        }

        try {
            if (! Schema::hasTable('contacts') || ! Schema::hasTable('workflows')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $contact = null;

        if ($event->customerEmail) {
            $contact = Contact::where('email', $event->customerEmail)->first();
        } elseif ($event->customerId) {
            $contact = Contact::where('user_id', $event->customerId)->first();
        }

        try {
            app(WorkflowEngine::class)->cartAbandoned($contact, [
                'cart_token' => $event->cartToken,
                'cart_data' => $event->cartData,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
