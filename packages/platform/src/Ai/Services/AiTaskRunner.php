<?php

namespace Nreljed\Platform\Ai\Services;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\QueuedAgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Nreljed\Platform\Ai\Exceptions\MissingAiProviderException;
use Nreljed\Platform\Ai\Jobs\InvokeAiTask;
use Nreljed\Platform\Models\User;
use Throwable;

/**
 * Module-facing entry point for running Laravel AI SDK agents against a
 * user's BYOK provider.
 *
 * Responsibilities:
 *   - Resolve the user's active provider and inject its decrypted key into the
 *     SDK config for this request.
 *   - Enforce the user's soft daily usage caps before prompting.
 *   - Invoke the agent synchronously, streaming, or queued.
 *   - Retry provider rate limits (HTTP 429) up to 3 attempts with a short
 *     increasing back-off.
 *   - Record best-effort usage (and last-error on failure) — never blocks.
 */
class AiTaskRunner
{
    public function __construct(
        protected AiService $aiService,
    ) {}

    /**
     * Prompt an agent for a user and return the synchronous response.
     *
     * @throws RateLimitedException
     * @throws MissingAiProviderException
     */
    public function prompt(
        Agent $agent,
        User $user,
        string $prompt,
        ?string $providerName = null,
        ?int $timeout = null,
    ): AgentResponse {
        $resolved = $this->prepare($agent, $user, $providerName);
        $exceeded = $this->aiService->exceedsCaps($user, 0, 0);
        if ($exceeded !== null) {
            throw new \RuntimeException(
                $this->capMessage($exceeded),
            );
        }
        $attempts = 0;
        while (true) {
            $attempts++;
            $start = microtime(true);
            try {
                $response = $agent->prompt(
                    $prompt,
                    provider: $resolved['provider'],
                    model: $resolved['model'],
                    timeout: $timeout,
                );
                break;
            } catch (RateLimitedException $e) {
                if ($attempts >= AiService::MAX_RATE_LIMIT_ATTEMPTS) {
                    $this->recordError($user, $resolved['provider'], $e);
                    throw $e;
                }
                usleep(($this->delaySeconds($attempts)) * 1_000_000);
                continue;
            } catch (Throwable $e) {
                $this->recordError($user, $resolved['provider'], $e);
                throw $e;
            }
        }

        $this->logUsage(
            $user,
            $agent,
            $resolved,
            $response->usage->promptTokens,
            $response->usage->completionTokens,
            $this->elapsedMs($start),
        );
        return $response;
    }

    /**
     * Stream an agent's response for a user (SSE).
     */
    public function stream(
        Agent $agent,
        User $user,
        string $prompt,
        ?string $providerName = null,
    ): StreamableAgentResponse {
        $resolved = $this->prepare($agent, $user, $providerName);
        $exceeded = $this->aiService->exceedsCaps($user, 0, 0);
        if ($exceeded !== null) {
            throw new \RuntimeException(
                $this->capMessage($exceeded),
            );
        }
        $start = microtime(true);
        $stream = $agent->stream(
            $prompt,
            provider: $resolved['provider'],
            model: $resolved['model'],
        );
        $stream->then(function ($response) use ($user, $agent, $resolved, $start): void {
            $usage = $response->usage ?? null;
            $this->logUsage(
                $user,
                $agent,
                $resolved,
                $usage->promptTokens ?? 0,
                $usage->completionTokens ?? 0,
                $this->elapsedMs($start),
            );
        });
        return $stream;
    }

    /**
     * Queue an agent's response for a user (background job).
     */
    public function queue(
        Agent $agent,
        User $user,
        string $prompt,
        ?string $providerName = null,
    ): QueuedAgentResponse {
        $exceeded = $this->aiService->exceedsCaps($user, 0, 0);
        if ($exceeded !== null) {
            throw new \RuntimeException(
                $this->capMessage($exceeded),
            );
        }
        return new QueuedAgentResponse(dispatch(new InvokeAiTask(
            userId: $user->id,
            agentClass: $agent::class,
            prompt: $prompt,
            providerName: $providerName,
        )));
    }

    /**
     * Resolve the user's provider and inject its key. Returns the provider +
     * model pair to pass to the agent.
     *
     * @return array{provider: string, model: string|null}
     */
    private function prepare(
        Agent $agent,
        User $user,
        ?string $providerName,
    ): array {
        $resolved = $this->aiService->resolveForUser($user, $providerName);
        config()->set(
            "ai.providers.{$resolved['provider']}.key",
            $resolved['key'],
        );
        return [
            'provider' => $resolved['provider'],
            'model' => $resolved['model'],
        ];
    }

    /**
     * Record usage (and last-error on failure) best-effort — never blocks.
     */
    private function logUsage(
        User $user,
        Agent $agent,
        array $resolved,
        int $inputTokens,
        int $outputTokens,
        ?int $latencyMs = null,
    ): void {
        try {
            $this->aiService->logUsage(
                user: $user,
                agentClass: $agent::class,
                provider: $resolved['provider'],
                model: $resolved['model'] ?? '',
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                latencyMs: $latencyMs,
            );
        } catch (Throwable) {
            // Usage logging is best-effort — never blocks AI operations.
        }
    }

    /**
     * @param  string  $provider
     */
    private function recordError(User $user, string $provider, Throwable $e): void
    {
        $this->aiService->recordError($user, $provider, $e->getMessage());
    }

    private function capMessage(array $exceeded): string
    {
        $parts = [];
        if (! empty($exceeded['requests'])) {
            $parts[] = 'daily request limit';
        }
        if (! empty($exceeded['tokens'])) {
            $parts[] = 'daily token limit';
        }
        return 'Your AI daily '.implode(' and ', $parts).' has been reached.';
    }

    private function delaySeconds(int $attempt): int
    {
        return AiService::RATE_LIMIT_BASE_DELAY_SECONDS * max(1, $attempt - 1);
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}