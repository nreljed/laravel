<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Ai\Agents\ChatAgent;
use Nreljed\Platform\Ai\Agents\ContentAgent;
use Nreljed\Platform\Ai\Agents\SeoAgent;
use Nreljed\Platform\Ai\Concerns\EnforcesAiCaps;
use Nreljed\Platform\Ai\Controllers\AiProviderController;
use Nreljed\Platform\Ai\Models\AiUsageSetting;
use Nreljed\Platform\Ai\Services\AiService;
use Nreljed\Platform\Ai\Services\AiTaskRunner;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Source-level contract tests for the USD spend cap.
 *
 * The spend cap is only real if *every* path that reaches an AI provider is
 * guarded — the package has three enforcement points (AiTaskRunner, the
 * EnforcesAiCaps trait used by the agents, and the usage-cap controller) plus
 * one accounting choke point (AiService::logUsage). These assertions pin that
 * wiring so a future refactor cannot silently reopen the bypass where an agent
 * called the SDK directly without a cap check.
 *
 * These are structural checks against the shipped source, in the style of
 * {@see SeoAgentSchemaContractTest}.
 */
final class AiSpendCapContractTest extends TestCase
{
    public function test_usage_setting_exposes_spend_cap_constants(): void
    {
        $this->assertSame(500, AiUsageSetting::DEFAULT_CAP_COST_CENTS);
        $this->assertSame(100000, AiUsageSetting::CAP_COST_MAX);
    }

    public function test_log_usage_prices_unpriced_events_via_the_catalog(): void
    {
        $source = $this->sourceOf(AiService::class);

        $this->assertStringContainsString(
            "'cost_cents' => \$costCents ?? AiPricingCatalog::costCents(\$provider, \$inputTokens, \$outputTokens)",
            $source,
            'logUsage() must price events the caller did not price itself.'
        );
    }

    public function test_exceeds_caps_checks_spend_and_never_tokens(): void
    {
        $source = $this->sourceOf(AiService::class);

        $this->assertStringContainsString(
            "\$today['totals']['cost_cents'] >= \$caps['cost_cents']",
            $source,
            'exceedsCaps() must trip on the daily spend cap.'
        );

        // Docblocks legitimately explain why the legacy column is ignored, so
        // only code-level reads are forbidden.
        foreach (["'cap_tokens'", "'cap_tokens' =>", '->cap_tokens'] as $read) {
            $this->assertStringNotContainsString(
                $read,
                $source,
                "The legacy token cap must not be read in code ({$read})."
            );
        }
    }

    public function test_caps_are_requests_plus_spend(): void
    {
        $source = $this->sourceOf(AiService::class);

        $this->assertStringContainsString(
            "'cost_cents' => \$setting->cap_cents ?? AiUsageSetting::DEFAULT_CAP_COST_CENTS",
            $source
        );

        $this->assertStringContainsString(
            "'cap_requests' => \$requests, 'cap_cents' => \$costCents",
            $source,
            'updateCaps() must persist the spend cap.'
        );
    }

    public function test_agents_share_one_enforcement_trait(): void
    {
        foreach ([ChatAgent::class, ContentAgent::class, SeoAgent::class] as $agent) {
            $this->assertContains(
                EnforcesAiCaps::class,
                (new ReflectionClass($agent))->getTraitNames(),
                "{$agent} must enforce caps."
            );
        }
    }

    public function test_every_agent_prompt_entry_point_is_guarded(): void
    {
        $expectedCalls = [
            ChatAgent::class => 2,       // chat(), streamChat()
            ContentAgent::class => 2,    // generate(), improve()
            SeoAgent::class => 1,        // generateSeo()
        ];

        foreach ($expectedCalls as $agent => $count) {
            $this->assertSame(
                $count,
                substr_count($this->sourceOf($agent), 'ensureWithinAiCaps('),
                "{$agent} must guard each of its {$count} prompt entry point(s)."
            );
        }
    }

    public function test_task_runner_asserts_caps_before_preparing_the_provider(): void
    {
        $source = $this->sourceOf(AiTaskRunner::class);

        $prompt = substr($source, strpos($source, 'function prompt('));
        $prompt = substr($prompt, 0, strpos($prompt, 'function stream('));

        $guard = strpos($prompt, 'assertWithinCaps(');
        $prepare = strpos($prompt, '$this->prepare(');

        $this->assertNotFalse($guard);
        $this->assertNotFalse($prepare);
        $this->assertLessThan(
            $prepare,
            $guard,
            'A capped user must be rejected before their provider key is decrypted.'
        );
    }

    public function test_queued_runs_are_guarded_at_dispatch_time(): void
    {
        $this->assertStringContainsString(
            'assertWithinCaps(',
            $this->methodBodyOf(AiTaskRunner::class, 'queue')
        );
    }

    public function test_usage_cap_endpoint_takes_cost_in_cents(): void
    {
        $source = $this->sourceOf(AiProviderController::class);

        $this->assertStringContainsString(
            "'cost_cents' => ['required', 'integer', 'min:1', 'max:'.AiUsageSetting::CAP_COST_MAX]",
            $source
        );

        $this->assertStringContainsString('costCents: $validated['."'cost_cents'".']', $source);

        $this->assertStringNotContainsString('cap_tokens', $source);
    }

    private function sourceOf(string $class): string
    {
        $file = (new ReflectionClass($class))->getFileName();

        $this->assertIsString($file);

        return (string) file_get_contents($file);
    }

    private function methodBodyOf(string $class, string $method): string
    {
        $source = $this->sourceOf($class);
        $start = strpos($source, "function {$method}(");

        $this->assertNotFalse($start, "Expected {$class}::{$method}() to exist.");

        $body = substr($source, $start);
        $end = strpos($body, "\n    }");

        return $end === false ? $body : substr($body, 0, $end);
    }
}
