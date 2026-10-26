<?php

namespace Nreljed\MarketingModule\Services;

use Nreljed\MarketingModule\Agents\MarketingStrategistAgent;
use Nreljed\MarketingModule\Enums\CampaignObjective;
use Nreljed\MarketingModule\Models\MarketingPlan;
use Nreljed\Platform\Models\User;

/**
 * Turns an AI strategist draft into a reviewable plan.
 *
 * The agent only proposes; this service persists the proposal as a draft that
 * an operator must approve before it creates campaigns or posts.
 */
class MarketingPlanService
{
    public function generate(User $user, int $days, ?string $goal, MarketingStrategistAgent $agent): MarketingPlan
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $draft = $agent->draftPlan($user, $days, $goal);

        return MarketingPlan::create([
            'name' => (string) ($draft['name'] ?? 'AI draft plan'),
            'objective' => (string) ($draft['objective'] ?? CampaignObjective::Awareness->value),
            'status' => 'draft',
            'audience' => (string) ($draft['audience'] ?? ''),
            'cadence' => (string) ($draft['cadence'] ?? ''),
            'channel_mix' => is_array($draft['channel_mix'] ?? null) ? $draft['channel_mix'] : [],
            'content_pillars' => is_array($draft['content_pillars'] ?? null) ? $draft['content_pillars'] : [],
            'kpi_targets' => is_array($draft['kpi_targets'] ?? null) ? $draft['kpi_targets'] : [],
            'constraints' => (string) ($draft['constraints'] ?? ''),
            'generated_by' => 'ai',
            'metadata' => [
                'rationale' => $draft['rationale'] ?? null,
                'next_steps' => $draft['next_steps'] ?? [],
                'evidence' => $draft['evidence'] ?? [],
            ],
        ]);
    }
}
