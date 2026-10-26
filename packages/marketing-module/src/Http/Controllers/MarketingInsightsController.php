<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\MarketingModule\Agents\MarketingAnalystAgent;
use Nreljed\MarketingModule\Models\MarketingInsight;
use Nreljed\MarketingModule\Services\MarketingAnalytics;
use Nreljed\MarketingModule\Services\MarketingInsightService;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\User;

class MarketingInsightsController extends Controller
{
    public function index(Request $request, MarketingInsightService $insights): Response
    {
        $this->authorize('viewAny', MarketingInsight::class);

        $days = (int) ($request->query('days', 30));
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        return Inertia::render('marketing/insights', [
            'period' => $days,
            'insights' => MarketingInsight::query()
                ->where('period_days', $days)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (MarketingInsight $insight): array => [
                    'id' => $insight->id,
                    'type' => $insight->type?->value,
                    'type_label' => $insight->type?->label(),
                    'title' => $insight->title,
                    'summary' => $insight->summary,
                    'recommendation' => $insight->recommendation,
                    'evidence' => $insight->evidence ?? [],
                    'confidence' => $insight->confidence?->value,
                    'confidence_label' => $insight->confidence?->label(),
                    'status' => $insight->status?->value,
                    'status_label' => $insight->status?->label(),
                    'generated_by' => $insight->generated_by,
                    'applied_at' => $insight->applied_at?->toDateTimeString(),
                    'period_start' => $insight->period_start?->toDateString(),
                    'period_end' => $insight->period_end?->toDateString(),
                ])
                ->all(),
        ]);
    }

    public function analyze(Request $request, MarketingAnalystAgent $agent): RedirectResponse
    {
        $this->authorize('create', MarketingInsight::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $days = (int) ($request->input('days', 30));
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $service = new MarketingInsightService(new MarketingAnalytics);
        $service->analyzeWithAgent($user, $days, $agent);

        return redirect()->back()->with('status', 'AI insights generated for review.');
    }

    public function refresh(Request $request, MarketingInsightService $insights): RedirectResponse
    {
        $this->authorize('create', MarketingInsight::class);

        $days = (int) ($request->input('days', 30));
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        $insights->refresh($days);

        return redirect()->back()->with('status', 'Insights refreshed.');
    }

    public function apply(Request $request, MarketingInsight $insight, MarketingInsightService $insights): RedirectResponse
    {
        $this->authorize('update', $insight);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $insights->apply($insight, $user);

        return redirect()->back()->with('status', 'Insight marked applied. Create a draft from the recommendation when ready.');
    }
}
