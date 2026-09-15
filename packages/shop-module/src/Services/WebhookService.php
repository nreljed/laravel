<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Support\Facades\Http;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Webhook;
use Nreljed\ShopModule\Models\WebhookCall;

class WebhookService
{
    public function dispatch(string $event, array $payload): void
    {
        $webhooks = Webhook::active()->get()->filter(fn (Webhook $w) => $w->handlesEvent($event));

        foreach ($webhooks as $webhook) {
            $this->createAndSend($webhook, $event, $payload);
        }
    }

    public function dispatchAndWait(Webhook $webhook, string $event, array $payload): WebhookCall
    {
        return $this->createAndSend($webhook, $event, $payload);
    }

    protected function createAndSend(Webhook $webhook, string $event, array $payload): WebhookCall
    {
        $call = WebhookCall::create([
            'webhook_id' => $webhook->id,
            'event' => $event,
            'payload' => $payload,
            'max_attempts' => $webhook->retry_attempts,
        ]);

        $this->send($call, $webhook);

        return $call;
    }

    public function send(WebhookCall $call, ?Webhook $webhook = null): void
    {
        $webhook ??= $call->webhook;

        $call->increment('attempts');

        $payloadJson = json_encode($call->payload);
        $signature = $webhook->generateSignature($payloadJson);

        $headers = array_merge($webhook->headers ?? [], [
            'Content-Type' => 'application/json',
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Event' => $call->event,
            'User-Agent' => 'CostBreaker360-Webhook/1.0',
        ]);

        try {
            $response = Http::timeout($webhook->timeout)
                ->withHeaders($headers)
                ->post($webhook->url, $call->payload);

            $call->update([
                'response_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 10000),
                'completed_at' => now(),
            ]);

            if ($response->successful()) {
                $call->update(['status' => 1]);
            } else {
                $this->handleFailure($call, $webhook);
            }
        } catch (\Exception $e) {
            $call->update([
                'response_status' => 0,
                'response_body' => substr($e->getMessage(), 0, 10000),
                'completed_at' => now(),
            ]);

            $this->handleFailure($call, $webhook);
        }
    }

    protected function handleFailure(WebhookCall $call, Webhook $webhook): void
    {
        if ($call->shouldRetry()) {
            $delay = $webhook->retry_delay * $call->attempts;
            $call->update([
                'scheduled_at' => now()->addSeconds($delay),
                'status' => 0,
            ]);
        } else {
            $call->update(['status' => 2]);
        }
    }

    public function processPending(): int
    {
        $processed = 0;

        $pending = WebhookCall::where('status', 0)
            ->where('scheduled_at', '<=', now())
            ->orWhereNull('scheduled_at')
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        foreach ($pending as $call) {
            if ($call->webhook) {
                $this->send($call);
                $processed++;
            }
        }

        return $processed;
    }

    public function dispatchOrderCreated(Order $order): void
    {
        $this->dispatch('order.created', [
            'event' => 'order.created',
            'data' => $this->buildOrderPayload($order),
        ]);
    }

    public function dispatchOrderStatusChanged(Order $order, string $oldStatus): void
    {
        $this->dispatch('order.status_changed', [
            'event' => 'order.status_changed',
            'data' => array_merge($this->buildOrderPayload($order), [
                'previous_status' => $oldStatus,
            ]),
        ]);
    }

    public function dispatchOrderRefunded(Order $order, float $amount): void
    {
        $this->dispatch('order.refunded', [
            'event' => 'order.refunded',
            'data' => array_merge($this->buildOrderPayload($order), [
                'refund_amount' => $amount,
            ]),
        ]);
    }

    protected function buildOrderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total' => (float) $order->total,
            'subtotal' => (float) $order->subtotal,
            'shipping_total' => (float) ($order->shipping_total ?? 0),
            'tax_total' => (float) ($order->tax_total ?? 0),
            'discount_total' => (float) ($order->discount_total ?? 0),
            'currency' => $order->currency ?? 'USD',
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
            ]),
        ];
    }
}
