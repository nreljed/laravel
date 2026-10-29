<?php

namespace Nreljed\Platform\Ai\Concerns;

use Nreljed\Platform\Ai\Services\AiService;
use Nreljed\Platform\Models\User;

/**
 * Trait for best-effort AI usage logging.
 *
 * Use this in any class that calls AI providers to automatically log token usage.
 * Failures are silently caught — logging should never block AI operations.
 */
trait LogsAiUsage
{
    /**
     * Log an AI usage event (best-effort).
     *
     * When `$costCents` is null the event is priced from the pinned provider
     * rates — callers normally omit it.
     */
    public function logAiUsage(
        User $user,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        ?float $costCents = null,
    ): void {
        try {
            app(AiService::class)->logUsage(
                user: $user,
                agentClass: static::class,
                provider: $provider,
                model: $model,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                costCents: $costCents,
            );
        } catch (\Throwable) {
            // Usage logging is best-effort — failures don't block requests
        }
    }
}
