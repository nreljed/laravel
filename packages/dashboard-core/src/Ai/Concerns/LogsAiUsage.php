<?php

namespace Nreljed\DashboardCore\Ai\Concerns;

use Nreljed\DashboardCore\Ai\Services\AiService;
use Nreljed\DashboardCore\Models\User;

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
     */
    public function logAiUsage(
        User $user,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        ?int $costCents = null,
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
