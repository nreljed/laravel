<?php

namespace Nreljed\MarketingModule\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Nreljed\MarketingModule\Services\MarketingAnalytics;
use Nreljed\Platform\Ai\Agents\Traits\ResolvesUserProvider;
use Nreljed\Platform\Ai\Concerns\EnforcesAiCaps;
use Nreljed\Platform\Ai\Concerns\LogsAiUsage;
use Nreljed\Platform\Models\User;

/**
 * A BYOK strategist that drafts a reviewable marketing plan.
 *
 * The agent only proposes structure. Approval is required, and no campaigns,
 * posts or budgets are created automatically.
 */
class MarketingStrategistAgent implements Agent, HasStructuredOutput
{
    use EnforcesAiCaps, LogsAiUsage, Promptable, ResolvesUserProvider;

    public function __construct(private readonly MarketingAnalytics $analytics) {}

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are a cautious marketing strategist for a social publishing tool.

Rules:
- Only propose what the available metrics and audience support. Cite evidence.
- Never invent exact spend numbers or guaranteed results.
- Do not create campaigns, posts, or automate publishing.
- Prefer concrete actions an operator can review and apply manually.
- Keep recommendations grounded in delivery, engagement, coverage and link performance.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'objective' => $schema->string()->required(),
            'audience' => $schema->string()->required(),
            'cadence' => $schema->string()->required(),
            'channel_mix' => $schema->array()->items($schema->string())->required(),
            'content_pillars' => $schema->array()->items($schema->string())->required(),
            'kpi_targets' => $schema->array()->items($schema->string())->required(),
            'constraints' => $schema->string()->required(),
            'rationale' => $schema->string()->required(),
            'next_steps' => $schema->array()->items($schema->string())->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function draftPlan(User $user, int $days = 30, ?string $goal = null): array
    {
        $this->forUser($user);
        $this->ensureWithinAiCaps($user);
        $this->injectProviderKey();

        $overview = $this->analytics->overview($days);
        $context = json_encode([
            'goal' => $goal,
            'period' => $overview['period'],
            'summary' => $overview['summary'],
            'coverage' => $overview['coverage'],
            'platforms' => $overview['platforms'],
            'top_posts' => array_slice($overview['top_posts'], 0, 5),
            'campaigns' => array_slice($overview['campaigns'], 0, 5),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $prompt = "Draft a reviewable marketing plan.\n\nContext:\n{$context}\n\n";
        if ($goal !== null && $goal !== '') {
            $prompt .= "Operator goal: {$goal}\n\n";
        }
        $prompt .= 'Return a structured plan the operator must approve before creating campaigns or posts.';

        $response = $this->prompt($prompt);
        $meta = $response instanceof StructuredAgentResponse ? $response->toArray() : [];

        $resolved = $this->getResolvedProvider();
        $this->logAiUsage(
            user: $user,
            provider: $resolved['driver'],
            model: $resolved['model'] ?? 'unknown',
            inputTokens: $response->usage->promptTokens,
            outputTokens: $response->usage->completionTokens,
        );

        $meta['evidence'] = [
            'period' => $overview['period'],
            'summary' => $overview['summary'],
            'coverage' => $overview['coverage'],
        ];

        return $meta;
    }
}
