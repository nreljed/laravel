<?php

namespace App\Ai\Concerns;

use App\Ai\Services\AiService;
use App\Models\User;

/**
 * Concern for resolving AI provider configurations outside of agent classes.
 *
 * Use this trait in controllers, services, or other non-agent contexts that need
 * to resolve a user's AI provider key. Agent classes should use
 * {@see ResolvesUserProvider} instead.
 */
trait HasAiProvider
{
    /**
     * Resolve the active AI provider for a user.
     *
     * @return array{provider: string, key: string, model: string|null}
     */
    public function resolveAiProvider(User $user, ?string $providerName = null): array
    {
        return app(AiService::class)->resolveForUser($user, $providerName);
    }
}
