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
 * A BYOK analyst that produces evidence-backed insights and recommendations.
 *
 * It never publishes or spends. "Apply" creates a draft object only when an
 * operator confirms it.
 */
class MarketingAnalystAgent implements Agent, HasStructuredOutput
{
    use EnforcesAiCaps, LogsAiUsage, Promptable, ResolvesUserProvider;

    public function __construct(private readonly MarketingAnalytics $analytics) {}

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are a conservative marketing analyst.

Rules:
- Base every claim on numbers in the provided evidence.
- Call out coverage gaps, missing metrics and when a rate is undefined.
- Recommend small, testable changes. Prefer a draft post or brief over a big rewrite.
- Never say "increase spend". You may say "test with a tracked post".
- Never publish, auto-schedule, or change budgets.
- Include a hypothesis and concrete evidence for each recommendation.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period_days' => $schema->integer()->required(),
            'insights' => $schema->array()->items($schema->object([
                'type' => $schema->string()->required(),
                'title' => $schema->string()->required(),
                'summary' => $schema->string()->required(),
                'recommendation' => $schema->string()->required(),
                'evidence' => $schema->array()->items($schema->string())->required(),
                'confidence' => $schema->string()->required(),
            ]))->required(),
            'risks' => $schema->array()->items($schema->string())->required(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(User $user, int $days = 30): array
    {
        $this->forUser($user);
        $this->ensureWithinAiCaps($user);
        $this->injectProviderKey();

        $overview = $this->analytics->overview($days);
        $context = json_encode([
            'period' => $overview['period'],
            'summary' => $overview['summary'],
            'coverage' => $overview['coverage'],
            'platforms' => $overview['platforms'],
            'top_posts' => $overview['top_posts'],
            'campaigns' => $overview['campaigns'],
            'workflows' => $overview['workflows'],
            'links' => $overview['links'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $prompt = "Analyze this marketing period and produce evidence-backed insights.\n\nData:\n{$context}\n\nReturn structured insights with evidence and confidence. Do not publish or spend.";

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

        $meta['period'] = $overview['period'];

        return $meta;
    }
}
