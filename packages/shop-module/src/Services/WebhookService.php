<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Support\Facades\Http;
use Nreljed\ShopModule\Events\WebhookEvents;
use Nreljed\ShopModule\Jobs\SendWebhook;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Webhook;
use Nreljed\ShopModule\Models\WebhookCall;

class WebhookService
{
    public function dispatch(string $event, array $payload): void
    {
        $webhooks = Webhook::active()->get()->filter(fn (Webhook $w) => $w->handlesEvent($event));

        foreach ($webhooks as $webhook) {
            $call = WebhookCall::create([
                'webhook_id' => $webhook->id,
                'event' => $event,
                'payload' => $payload,
                'max_attempts' => $webhook->retry_attempts,
            ]);

            SendWebhook::dispatch(
                $call->id,
                $webhook->id,
                $webhook->retry_attempts,
                $webhook->retry_delay,
            );
        }
    }

    public function dispatchAndWait(Webhook $webhook, string $event, array $payload): WebhookCall
    {
        $call = WebhookCall::create([
            'webhook_id' => $webhook->id,
            'event' => $event,
            'payload' => $payload,
            'max_attempts' => $webhook->retry_attempts,
        ]);

        $success = $this->send($call, $webhook);

        $call->update([
            'status' => $success ? 1 : 2,
            'completed_at' => now(),
        ]);

        return $call;
    }

    /**
     * Perform a single delivery attempt and record the outcome.
     *
     * Returns whether the endpoint answered with a 2xx. Retries and the
     * permanent-failure path are owned by the `SendWebhook` queue job; the
     * manual "test" button uses `dispatchAndWait()` for one synchronous shot.
     */
    public function send(WebhookCall $call, ?Webhook $webhook = null): bool
    {
        $webhook ??= Webhook::find($call->webhook_id);

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
                $this->recordSuccess($webhook);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            $call->update([
                'response_status' => 0,
                'response_body' => substr($e->getMessage(), 0, 10000),
                'completed_at' => now(),
            ]);

            return false;
        }
    }

    /**
     * Safety net for `webhooks:process`: re-queues any delivery that is still
     * pending (no running job, or a lost job) so a real queue retries it.
     */
    public function processPending(): int
    {
        $requeued = 0;

        $pending = WebhookCall::where('status', 0)
            ->where('scheduled_at', '<=', now())
            ->orWhereNull('scheduled_at')
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        foreach ($pending as $call) {
            $webhook = Webhook::find($call->webhook_id);

            if (! $webhook || ! $webhook->is_active) {
                continue;
            }

            SendWebhook::dispatch(
                $call->id,
                $webhook->id,
                $webhook->retry_attempts,
                $webhook->retry_delay,
            );

            $requeued++;
        }

        return $requeued;
    }

    protected function recordSuccess(Webhook $webhook): void
    {
        if ($webhook->consecutive_failures > 0) {
            $webhook->update(['consecutive_failures' => 0]);
        }
    }

    public function dispatchOrderPlaced(Order $order): void
    {
        $this->dispatch(WebhookEvents::ORDER_PLACED, [
            'event' => WebhookEvents::ORDER_PLACED,
            'data' => $this->buildOrderPayload($order),
        ]);
    }

    public function dispatchOrderStatusChanged(Order $order, string $oldStatus): void
    {
        $this->dispatch(WebhookEvents::ORDER_STATUS_CHANGED, [
            'event' => WebhookEvents::ORDER_STATUS_CHANGED,
            'data' => array_merge($this->buildOrderPayload($order), [
                'previous_status' => $oldStatus,
            ]),
        ]);
    }

    public function dispatchOrderRefunded(Order $order, float $amount): void
    {
        $this->dispatch(WebhookEvents::ORDER_REFUNDED, [
            'event' => WebhookEvents::ORDER_REFUNDED,
            'data' => array_merge($this->buildOrderPayload($order), [
                'refund_amount' => $amount,
            ]),
        ]);
    }

    public function dispatchShipment(string $event, array $payload): void
    {
        $this->dispatch($event, [
            'event' => $event,
            'data' => $payload,
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
            'currency' => $order->currency ?? app(CurrencyService::class)->defaultCode(),
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
