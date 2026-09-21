<?php

namespace Nreljed\Platform\Ai\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
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
     */
    public function logUsage(
        User $user,
        string $agentClass,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        ?int $costCents = null,
        ?int $latencyMs = null,
    ): AiUsageLog {
        return AiUsageLog::create([
            'user_id' => $user->id,
            'agent_class' => $agentClass,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => max(0, $inputTokens),
            'output_tokens' => max(0, $outputTokens),
            'cost_cents' => $costCents,
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
     * When the user has not saved caps yet, defaults are used. Returns null when
     * within budget, otherwise an array describing which cap is exceeded.
     *
     * @return array{requests: bool, tokens: bool}|null
     */
    public function exceedsCaps(User $user, int $inputTokens, int $outputTokens): ?array
    {
        $caps = $this->getCaps($user);
        $today = $this->getDailyUsage($user);

        $exceeded = [];

        if ($today['totals']['requests'] >= $caps['requests']) {
            $exceeded['requests'] = true;
        }

        if (($today['totals']['tokens'] + $inputTokens + $outputTokens) > $caps['tokens']) {
            $exceeded['tokens'] = true;
        }

        return $exceeded === [] ? null : $exceeded;
    }

    /**
     * Get a user's daily caps (defaults fallback).
     *
     * @return array{requests: int, tokens: int}
     */
    public function getCaps(User $user): array
    {
        $setting = $user->aiUsageSetting;

        return [
            'requests' => $setting->cap_requests ?? AiUsageSetting::DEFAULT_CAP_REQUESTS,
            'tokens' => $setting->cap_tokens ?? AiUsageSetting::DEFAULT_CAP_TOKENS,
        ];
    }

    /**
     * Persist a user's soft daily caps, clamped into legal ranges.
     *
     * @return array{requests: int, tokens: int}
     */
    public function updateCaps(User $user, int $requests, int $tokens): array
    {
        $requests = max(1, min(AiUsageSetting::CAP_REQUESTS_MAX, $requests));
        $tokens = max(1000, min(AiUsageSetting::CAP_TOKENS_MAX, $tokens));

        $user->aiUsageSetting()->updateOrCreate(
            ['user_id' => $user->id],
            ['cap_requests' => $requests, 'cap_tokens' => $tokens],
        );

        return ['requests' => $requests, 'tokens' => $tokens];
    }

    /**
     * Today's per-provider usage + totals for a user.
     *
     * @return array{date: string, providers: array<string, array{requests: int, input_tokens: int, output_tokens: int, last_model: string|null, last_run_at: string|null, last_latency_ms: int|null, last_error: string|null}>, totals: array{requests: int, tokens: int}}
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
     * @return array{providers: array<string, array{requests: int, input_tokens: int, output_tokens: int, last_model: string|null, last_run_at: string|null, last_latency_ms: int|null, last_error: string|null}>, totals: array{requests: int, tokens: int}}
     */
    public function getAllTimeUsage(User $user): array
    {
        return $this->rollup($user->aiUsageLogs()->latest()->get());
    }

    /**
     * Get a usage snapshot shaped for the Account → AI Keys UI.
     *
     * @return array{cap: array{requests: int, tokens: int}, today: array{date: string, providers: array<string, array{requests: int, input_tokens: int, output_tokens: int, last_model: string|null, last_run_at: string|null, last_latency_ms: int|null, last_error: string|null}>, totals: array{requests: int, tokens: int}}, all_time: array{providers: array<string, array{requests: int, input_tokens: int, output_tokens: int, last_model: string|null, last_run_at: string|null, last_latency_ms: int|null, last_error: string|null}>, totals: array{requests: int, tokens: int}}}
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
     * @return array{providers: array<string, array{requests: int, input_tokens: int, output_tokens: int, last_model: string|null, last_run_at: string|null, last_latency_ms: int|null, last_error: string|null}>, totals: array{requests: int, tokens: int}}
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
                ];

                $successCounts[$provider]['requests']++;
                $successCounts[$provider]['input_tokens'] += $log->input_tokens;
                $successCounts[$provider]['output_tokens'] += $log->output_tokens;
            }

            $lastByProvider[$provider] ??= [
                'last_model' => $log->model ?: null,
                    'last_run_at' => $log->created_at->toDateTimeString(),
                'last_latency_ms' => $log->latency_ms,
                'last_error' => $log->error,
            ];
        }

        $providers = [];
        $totals = ['requests' => 0, 'tokens' => 0];

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
        }

        return ['providers' => $providers, 'totals' => $totals];
    }
}
