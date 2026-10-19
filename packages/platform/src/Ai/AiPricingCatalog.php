<?php

namespace Nreljed\Platform\Ai;

use Nreljed\Platform\Ai\Services\AiService;

/**
 * Pinned cost-estimate catalog for AI providers.
 *
 * Single source of truth for the per-provider rates used to price a usage
 * event into `ai_usage_logs.cost_cents`, which feeds the daily spend cap
 * ({@see AiService::exceedsCaps()}) and the
 * cost columns in the Account → Integrations usage UI.
 *
 * Rates are integer cents per 1M tokens — an estimate (provider price sheets
 * drift), denominated per token because that is the only unit every provider
 * bills in. A provider absent from the catalog is free/local or unknown and
 * prices at 0: the spend cap still bounds paid traffic, and the request cap
 * bounds free traffic.
 *
 * Sub-cent events are common (a small Gemini call costs fractions of a cent),
 * so costCents() returns a float and the column stores fractional cents —
 * rounding per event would systematically under-count a day of small calls
 * to zero.
 */
final class AiPricingCatalog
{
    /**
     * Pinned rates in integer cents per 1M tokens, keyed by the provider id
     * used across `ai_providers.provider`, usage rollups, and SDK drivers.
     *
     * Ollama is intentionally absent — local inference is never billed.
     *
     * @var array<string, array{input_per_mtok_cents: int, output_per_mtok_cents: int, cache_read_per_mtok_cents: int}>
     */
    private const array PRICING = [
        'openai' => ['input_per_mtok_cents' => 125, 'output_per_mtok_cents' => 1000, 'cache_read_per_mtok_cents' => 12],
        'anthropic' => ['input_per_mtok_cents' => 300, 'output_per_mtok_cents' => 1500, 'cache_read_per_mtok_cents' => 30],
        'gemini' => ['input_per_mtok_cents' => 10, 'output_per_mtok_cents' => 40, 'cache_read_per_mtok_cents' => 1],
        'mistral' => ['input_per_mtok_cents' => 10, 'output_per_mtok_cents' => 30, 'cache_read_per_mtok_cents' => 1],
        'deepseek' => ['input_per_mtok_cents' => 27, 'output_per_mtok_cents' => 110, 'cache_read_per_mtok_cents' => 7],
        'xai' => ['input_per_mtok_cents' => 300, 'output_per_mtok_cents' => 1500, 'cache_read_per_mtok_cents' => 30],
    ];

    /**
     * Pinned rates for a provider, or null when the provider is unknown or
     * free/local (never billed).
     *
     * @return array{input_per_mtok_cents: int, output_per_mtok_cents: int, cache_read_per_mtok_cents: int}|null
     */
    public static function pricing(string $provider): ?array
    {
        return self::PRICING[$provider] ?? null;
    }

    /**
     * Whether the catalog prices this provider (false for free/local and
     * unknown providers).
     */
    public static function isPriced(string $provider): bool
    {
        return isset(self::PRICING[$provider]);
    }

    /**
     * Price one request in cents (fractional; see the class docblock).
     *
     * Cache-read tokens are a subset of the caller's inclusive input tokens
     * and are billed at the (cheaper) cache rate when the catalog pins one.
     * Free/local and unknown providers price at 0.0.
     */
    public static function costCents(string $provider, int $inputTokens, int $outputTokens, int $cacheReadTokens = 0): float
    {
        $pricing = self::pricing($provider);

        if ($pricing === null) {
            return 0.0;
        }

        $cacheRead = min(max(0, $cacheReadTokens), max(0, $inputTokens));
        $billableInput = max(0, $inputTokens) - $cacheRead;

        return ($billableInput * $pricing['input_per_mtok_cents']
            + $cacheRead * $pricing['cache_read_per_mtok_cents']
            + max(0, $outputTokens) * $pricing['output_per_mtok_cents']) / 1_000_000;
    }
}
