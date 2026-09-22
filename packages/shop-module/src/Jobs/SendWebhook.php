<?php

namespace Nreljed\ShopModule\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Nreljed\ShopModule\Models\Webhook;
use Nreljed\ShopModule\Models\WebhookCall;
use Nreljed\ShopModule\Services\WebhookService;

/**
 * Queued webhook delivery (PR 4 / 0.4).
 *
 * One job per webhook call. Unique per call so a re-dispatch (e.g. the
 * `webhooks:process` safety net) cannot double-send an in-flight call;
 * retries and acceleration come from the per-webhook `retry_attempts` /
 * `retry_delay` configuration through Laravel's queue backoff.
 */
class SendWebhook implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries;

    public int $backoff;

    public function __construct(
        protected int $webhookCallId,
        protected int $webhookId,
        int $retryAttempts = 3,
        int $retryDelay = 10,
    ) {
        $this->tries = max(1, $retryAttempts);
        $this->backoff = max(1, $retryDelay);
    }

    public function uniqueId(): string
    {
        return 'webhook-call-'.$this->webhookCallId;
    }

    public function handle(WebhookService $webhookService): void
    {
        $call = WebhookCall::find($this->webhookCallId);

        if (! $call) {
            return;
        }

        $webhook = Webhook::find($this->webhookId);

        if (! $webhook || ! $webhook->is_active) {
            return;
        }

        if (! $webhookService->send($call, $webhook)) {
            throw new \RuntimeException('Webhook delivery failed for call #'.$call->id);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $call = WebhookCall::find($this->webhookCallId);

        if ($call && $call->status !== 1) {
            $call->update(['status' => 2, 'completed_at' => now()]);
        }

        $webhook = Webhook::find($this->webhookId);

        if ($webhook) {
            $failures = $webhook->consecutive_failures + 1;

            $updates = [
                'consecutive_failures' => $failures,
                'is_active' => $failures < 10,
            ];

            if ($failures >= 10) {
                Log::warning("[Webhook] Endpoint auto-deactivated after {$failures} consecutive failures: ".$webhook->url);
            }

            $webhook->update($updates);
        }

        Log::error('[SendWebhook] Delivery permanently failed for call #'.$this->webhookCallId.' | '.$exception->getMessage());
    }
}