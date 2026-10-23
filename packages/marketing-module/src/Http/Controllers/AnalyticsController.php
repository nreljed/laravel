<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\MarketingModule\Services\AnalyticsCsvExporter;
use Nreljed\MarketingModule\Services\MarketingAnalytics;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The reporting page and its CSV exports.
 *
 * It separates what the engine did (deliveries, campaigns, workflows) from
 * what the audience did, because the module cannot observe the latter.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request, MarketingAnalytics $analytics): Response
    {
        $this->authorizeAnalytics();

        $days = (int) $request->integer('days', 30);
        $days = in_array($days, MarketingAnalytics::PERIODS, true) ? $days : 30;

        return Inertia::render('marketing/analytics', [
            'analytics' => $analytics->overview($days),
        ]);
    }

    public function export(
        Request $request,
        AnalyticsCsvExporter $exporter,
    ): StreamedResponse {
        $this->authorizeAnalytics();

        $validated = $request->validate([
            'dataset' => ['required', 'string', Rule::in(AnalyticsCsvExporter::DATASETS)],
            'days' => ['nullable', 'integer', Rule::in(MarketingAnalytics::PERIODS)],
        ]);

        $dataset = (string) $validated['dataset'];
        $days = (int) ($validated['days'] ?? 30);

        return response()->streamDownload(
            $exporter->stream($dataset, $days),
            $exporter->filename($dataset),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function authorizeAnalytics(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->hasPermission('marketing.analytics.view'), 403);
    }
}
