<?php

namespace Nreljed\Platform\Ai\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Nreljed\Platform\Ai\AiPricingCatalog;
use Nreljed\Platform\Ai\Exceptions\MissingAiProviderException;
use Nreljed\Platform\Ai\Models\AiUsageLog;
use Nreljed\Platform\Ai\Models\AiUsageSetting;
use Nreljed\Platform\Models\User;

/**
 * Centralized AI service for provider resolution, usage tracking, and caps.
 *
 * Today's + all-time usage are derived from the relational {@see AiUsageLog}
 * table, while the user-settable daily ceiling lives on {@see AiUsageSetting}.
 */
class AiService
{
    public const int MAX_RATE_LIMIT_ATTEMPTS = 3;

    public const int RATE_LIMIT_BASE_DELAY_SECONDS = 2;

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
            'model' => $provider->preferred_model ?? AiHttpClient::getDefaultModel($provider->provider),
        ];
    }

    /**
     * Log an AI usage event (best-effort, failures are silent).
     *
     * When `$costCents` is null the event is priced from the pinned rates in
     * {@see AiPricingCatalog} — every writer gets cost accounting for free.
     * Fractional cents are stored: sub-cent events are common and rounding
     * them per event would zero out a day of small calls.
     */
    public function logUsage(
        User $user,
        string $agentClass,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        ?float $costCents = null,
        ?int $latencyMs = null,
    ): AiUsageLog {
        return AiUsageLog::create([
            'user_id' => $user->id,
            'agent_class' => $agentClass,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => max(0, $inputTokens),
            'output_tokens' => max(0, $outputTokens),
            'cost_cents' => $costCents ?? AiPricingCatalog::costCents($provider, $inputTokens, $outputTokens),
            'latency_ms' => $latencyMs,
            'created_at' => now(),
        ]);
    }

    /**
     * Record a failed AI request's latest error for a user/provider (best-effort).
     *
     * Error rows are excluded from request/token rollups but surfaced as the
     * "last error" in the usage UI. Never throws.
     */
    public function recordError(User $user, string $provider, string $error): void
    {
        try {
            AiUsageLog::create([
                'user_id' => $user->id,
                'agent_class' => 'ai',
                'provider' => $provider,
                'model' => '',
                'input_tokens' => 0,
                'output_tokens' => 0,
                'error' => mb_substr($error, 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Never blocks AI operations.
        }
    }

    /**
     * Check whether a pending request would breach the user's soft daily caps.
     *
     * Caps are requests/day and USD spend/day. The spend check prices the
     * day's accumulated cost_cents — a pending request's cost cannot be
     * known before the provider answers, so the cap trips between requests
     * (soft ceiling, slightly overshootable by one in-flight call).
     *
     * When the user has not saved caps yet, defaults are used. Returns null
     * when within budget, otherwise an array describing which cap is exceeded.
     *
     * @return array{requests: bool, cost: bool}|null
     */
    public function exceedsCaps(User $user): ?array
    {
        $caps = $this->getCaps($user);
        $today = $this->getDailyUsage($user);

        $exceeded = [];

        if ($today['totals']['requests'] >= $caps['requests']) {
            $exceeded['requests'] = true;
        }

        if ($today['totals']['cost_cents'] >= $caps['cost_cents']) {
            $exceeded['cost'] = true;
        }

        return $exceeded === [] ? null : $exceeded;
    }

    /**
     * Get a user's daily caps (defaults fallback).
     *
     * The legacy `cap_tokens` value is intentionally not surfaced — token
     * ceilings were replaced by the USD spend cap.
     *
     * @return array{requests: int, cost_cents: int}
     */
    public function getCaps(User $user): array
    {
        $setting = $user->aiUsageSetting;

        return [
            'requests' => $setting->cap_requests ?? AiUsageSetting::DEFAULT_CAP_REQUESTS,
            'cost_cents' => $setting->cap_cents ?? AiUsageSetting::DEFAULT_CAP_COST_CENTS,
        ];
    }

    /**
     * Persist a user's soft daily caps, clamped into legal ranges.
     *
     * @param  int  $costCents  Daily spend ceiling in cents ($0.01–$1000).
     * @return array{requests: int, cost_cents: int}
     */
    public function updateCaps(User $user, int $requests, int $costCents): array
    {
        $requests = max(1, min(AiUsageSetting::CAP_REQUESTS_MAX, $requests));
        $costCents = max(1, min(AiUsageSetting::CAP_COST_MAX, $costCents));

        $user->aiUsageSetting()->updateOrCreate(
            ['user_id' => $user->id],
            ['cap_requests' => $requests, 'cap_cents' => $costCents],
        );

        return ['requests' => $requests, 'cost_cents' => $costCents];
    }

    /**
     * Human-readable message for a cap breach, with the live amounts.
     *
     * Shared by every enforcement path (AiTaskRunner and the agents'
     * EnforcesAiCaps trait) so the wording cannot drift between them.
     *
     * @param  array{requests?: bool, cost?: bool}  $exceeded
     */
    public function capMessage(User $user, array $exceeded): string
    {
        $caps = $this->getCaps($user);
        $parts = [];

        if (! empty($exceeded['requests'])) {
            $parts[] = 'daily request limit ('.$caps['requests'].')';
        }

        if (! empty($exceeded['cost'])) {
            $parts[] = 'daily spend limit ($'.number_format($caps['cost_cents'] / 100, 2).')';
        }

        return 'Your AI '.implode(' and ', $parts).' has been reached.';
    }

    /**
     * Today's per-provider usage + totals for a user.
     *
     * @return array{date: string, providers: array<string, array{requests: int, input_tokens: int, output_tokens: int, cost_cents: float, last_model: string|null, last_run_at: string|null, last_latency_ms: int|null, last_error: string|null}>, totals: array{requests: int, tokens: int, cost_cents: float}}
     */
    public function getDailyUsage(User $user, ?CarbonImmutable $date = null): array
    {
        $date ??= CarbonImmutable::now();
        $logs = $user->aiUsageLogs()
            ->where('created_at', '>=', $date->startOfDay())
            ->where('created_at', '<', $date->addDay()->startOfDay())
            ->latest()
            ->get();

        $rollup = $this->rollup($logs);

        return [
            'date' => $date->startOfDay()->toDateString(),
            'providers' => $rollup['providers'],
            'totals' => $rollup['totals'],
        ];
    }

    /**
     * All-time per-provider usage + totals for a user.
     *
     * @return array{providers: array<string, array{requests: int, input_tokens: int, output_tokens: int, cost_cents: float, last_model: string|null, last_run_at: string|null, last_latency_ms: int|null, last_error: string|null}>, totals: array{requests: int, tokens: int, cost_cents: float}}
     */
    public function getAllTimeUsage(User $user): array
    {
        return $this->rollup($user->aiUsageLogs()->latest()->get());
    }

    /**
     * Get a usage snapshot shaped for the Account → Integrations UI.
     *
     * @return array{cap: array{requests: int, cost_cents: int}, today: array{date: string, providers: array<string, array{requests: int, input_tokens: int, output_tokens: int, cost_cents: float, last_model: string|null, last_run_at: string|null, last_latency_ms: int|null, last_error: string|null}>, totals: array{requests: int, tokens: int, cost_cents: float}}, all_time: array{providers: array<string, array{requests: int, input_tokens: int, output_tokens: int, cost_cents: float, last_model: string|null, last_run_at: string|null, last_latency_ms: int|null, last_error: string|null}>, totals: array{requests: int, tokens: int, cost_cents: float}}}
     */
    public function getUsageSnapshot(User $user): array
    {
        return [
            'cap' => $this->getCaps($user),
            'today' => $this->getDailyUsage($user),
            'all_time' => $this->getAllTimeUsage($user),
        ];
    }

    /**
     * @param  Collection<int, AiUsageLog>  $logs
     * @return array{providers: array<string, array{requests: int, input_tokens: int, output_tokens: int, cost_cents: float, last_model: string|null, last_run_at: string|null, last_latency_ms: int|null, last_error: string|null}>, totals: array{requests: int, tokens: int, cost_cents: float}}
     */
    private function rollup(Collection $logs): array
    {
        $providers = [];
        $successCounts = [];
        $lastByProvider = [];

        foreach ($logs as $log) {
            $provider = $log->provider ?: 'unknown';

            if ($log->error === null) {
                $successCounts[$provider] ??= [
                    'requests' => 0,
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'cost_cents' => 0.0,
                ];

                $successCounts[$provider]['requests']++;
                $successCounts[$provider]['input_tokens'] += $log->input_tokens;
                $successCounts[$provider]['output_tokens'] += $log->output_tokens;
                $successCounts[$provider]['cost_cents'] += (float) ($log->cost_cents ?? 0.0);
            }

            $lastByProvider[$provider] ??= [
                'last_model' => $log->model ?: null,
                'last_run_at' => $log->created_at->toDateTimeString(),
                'last_latency_ms' => $log->latency_ms,
                'last_error' => $log->error,
            ];
        }

        $providers = [];
        $totals = ['requests' => 0, 'tokens' => 0, 'cost_cents' => 0.0];

        foreach ($successCounts as $provider => $counts) {
            $entry = array_merge($counts, $lastByProvider[$provider] ?? [
                'last_model' => null,
                'last_run_at' => null,
                'last_latency_ms' => null,
                'last_error' => null,
            ]);

            $providers[$provider] = $entry;

            $totals['requests'] += $counts['requests'];
            $totals['tokens'] += $counts['input_tokens'] + $counts['output_tokens'];
            $totals['cost_cents'] += $counts['cost_cents'];
        }

        return ['providers' => $providers, 'totals' => $totals];
    }
}
