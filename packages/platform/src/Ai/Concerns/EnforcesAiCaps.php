<?php

namespace Nreljed\Platform\Ai\Concerns;

use Nreljed\Platform\Ai\Services\AiService;
use Nreljed\Platform\Models\User;
use RuntimeException;

/**
 * Enforce the user's soft daily usage caps (requests / USD spend) before an
 * agent reaches its provider.
 *
 * `AiTaskRunner` enforces caps on its own paths, but the agents' direct
 * entry points (`ChatAgent::chat()`, `ContentAgent::generate()`, …) call the
 * SDK themselves — without this guard they would bypass the spend cap
 * entirely. Every agent public method that prompts must call
 * `ensureWithinAiCaps($user)` right after `forUser($user)`.
 *
 * The check mirrors `AiTaskRunner::assertWithinCaps()` exactly — same
 * service, same message — so both enforcement paths cannot drift.
 */
trait EnforcesAiCaps
{
    /**
     * @throws RuntimeException when the user's daily request or spend cap is reached
     */
    protected function ensureWithinAiCaps(User $user): void
    {
        $aiService = app(AiService::class);

        $exceeded = $aiService->exceedsCaps($user);

        if ($exceeded !== null) {
            throw new RuntimeException($aiService->capMessage($user, $exceeded));
        }
    }
}
