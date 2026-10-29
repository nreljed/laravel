<?php

namespace Nreljed\Platform\Tests;

use Nreljed\Platform\Ai\AiPricingCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the pinned pricing catalog that prices every usage event
 * into `ai_usage_logs.cost_cents` and therefore decides when the daily spend
 * cap trips. Pure static math — no framework boot required.
 */
final class AiPricingCatalogTest extends TestCase
{
    public function test_paid_providers_have_pinned_rates(): void
    {
        foreach (['openai', 'anthropic', 'gemini', 'mistral', 'deepseek', 'xai'] as $provider) {
            $this->assertNotNull(
                AiPricingCatalog::pricing($provider),
                "Expected pinned pricing for {$provider}."
            );
            $this->assertTrue(AiPricingCatalog::isPriced($provider));
        }
    }

    public function test_rates_are_cents_per_million_tokens(): void
    {
        $this->assertSame(
            [
                'input_per_mtok_cents' => 125,
                'output_per_mtok_cents' => 1000,
                'cache_read_per_mtok_cents' => 12,
            ],
            AiPricingCatalog::pricing('openai')
        );
    }

    public function test_local_provider_is_never_priced(): void
    {
        $this->assertNull(AiPricingCatalog::pricing('ollama'));
        $this->assertFalse(AiPricingCatalog::isPriced('ollama'));
        $this->assertSame(0.0, AiPricingCatalog::costCents('ollama', 1_000_000, 1_000_000));
    }

    public function test_unknown_provider_prices_at_zero(): void
    {
        $this->assertSame(0.0, AiPricingCatalog::costCents('not-a-provider', 5000, 5000));
    }

    public function test_one_million_tokens_each_way_matches_the_pinned_rates(): void
    {
        // 1M input (125c) + 1M output (1000c).
        $this->assertSame(1125.0, AiPricingCatalog::costCents('openai', 1_000_000, 1_000_000));
    }

    public function test_returns_fractional_cents_for_small_calls(): void
    {
        // 1000 * 10 + 500 * 40 = 30_000 / 1M = 0.03 cents — would round to
        // zero if cost were stored as integer cents.
        $this->assertEqualsWithDelta(0.03, AiPricingCatalog::costCents('gemini', 1000, 500), 1e-9);

        $this->assertEqualsWithDelta(
            0.625,
            AiPricingCatalog::costCents('openai', 1000, 500),
            1e-9
        );
    }

    public function test_cache_read_tokens_are_billed_at_the_cache_rate(): void
    {
        // 800 billable input @300 + 200 cache-read @30 + 0 output.
        $this->assertEqualsWithDelta(0.246, AiPricingCatalog::costCents('anthropic', 1000, 0, 200), 1e-9);
    }

    public function test_cache_read_tokens_cannot_exceed_input_tokens(): void
    {
        // cacheRead clamps to input, so the whole input is billed at cache rate
        // rather than producing a negative billable input.
        $expected = (100 * 12) / 1_000_000;

        $this->assertEqualsWithDelta($expected, AiPricingCatalog::costCents('openai', 100, 0, 999_999), 1e-12);
    }

    public function test_negative_token_counts_are_clamped(): void
    {
        $this->assertSame(0.0, AiPricingCatalog::costCents('openai', -1000, -500));
        $this->assertSame(0.0, AiPricingCatalog::costCents('openai', -10, 0, -5));
    }

    public function test_sub_cent_output_is_not_rounded_away(): void
    {
        // 1 output token on the cheapest provider is 0.00004 cents.
        $cost = AiPricingCatalog::costCents('gemini', 0, 1);

        $this->assertGreaterThan(0.0, $cost);
        $this->assertEqualsWithDelta(0.00004, $cost, 1e-9);
    }
}
