<?php

namespace Nreljed\Platform\Ai\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Contracts\Agent;
use Nreljed\Platform\Ai\Services\AiTaskRunner;
use Nreljed\Platform\Models\User;

/**
 * BYOK-safe queued agent invocation.
 *
 * The SDK's own queued path (`InvokeAgent` job) serializes the entire agent
 * instance — which carries the decrypted BYOK provider key injected by
 * `AiTaskRunner::prepare()` — straight into the queue payload, and ships no
 * user identifier, so the worker can never re-resolve the user's key. This
 * leaks the per-user key through the queue and breaks BYOK on the worker.
 *
 * This job carries ONLY scalar identifiers:
 *
 *   - user_id          (re-resolve the user on the worker)
 *   - agent class FQCN (re-instantiate the agent on the worker)
 *   - prompt
 *   - provider name
 *
 * No key, no agent object, no user object is ever serialized. On the worker,
 * `handle()` delegates to `AiTaskRunner::prompt()`, which re-resolves the
 * user's active provider, decrypts the key, injects it into the SDK config,
 * applies usage caps, retries rate limitsfff and records usage — the exact
 * same surface the synchronous path uses.
 */
class InvokeAiTask implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $userId  The user whose BYOK provider should run this task.
     * @param  class-string  $agentClass  FQCN of the platform agent (empty-arg constructible).
     * @param  string  $prompt  The prompt to run against the agent.
     * @param  string|null  $providerName  Optional explicit provider name.
     */
    public function __construct(
        public int $userId,
        public string $agentClass,
        public string $prompt,
        public ?string $providerName = null,
    ) {}

    /**
     * Execute the job: re-resolve the user's provider, inject the decrypted
     * key into the SDK config, then run the agent synchronously.
     */
    public function handle(AiTaskRunner $runner): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $agent = app($this->agentClass);

        if (! $agent instanceof Agent) {
            return;
        }

        $runner->prompt(
            $agent,
            $user,
            $this->prompt,
            $this->providerName,
        );
    }
}
