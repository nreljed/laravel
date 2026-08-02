<?php

namespace Nreljed\DashboardCore\Ai\Services;

use Nreljed\DashboardCore\Ai\Exceptions\MissingAiProviderException;
use Nreljed\DashboardCore\Ai\Models\AiUsageLog;
use Nreljed\DashboardCore\Models\User;

/**
 * Centralized AI service for provider resolution and usage tracking.
 */
class AiService
{
    /**
     * Resolve the active AI provider configuration for a user.
     *
     * @return array{provider: string, key: string, model: string|null}
     *
     * @throws MissingAiProviderException
     */
    public function resolveForUser(User $user, ?string $providerName = null): array
    {
        $query = $user->aiProviders()->active();

        if ($providerName) {
            $query->forProvider($providerName);
        }

        $provider = $query->first();

        if (! $provider) {
            throw new MissingAiProviderException($providerName ?? 'openai');
        }

        return [
            'provider' => $provider->provider,
            'key' => $provider->getDecryptedKey(),
            'model' => $provider->preferred_model,
        ];
    }

    /**
     * Log an AI usage event (best-effort, failures are silent).
     */
    public function logUsage(
        User $user,
        string $agentClass,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        ?int $costCents = null,
    ): AiUsageLog {
        return AiUsageLog::create([
            'user_id' => $user->id,
            'agent_class' => $agentClass,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_cents' => $costCents,
        ]);
    }
}
