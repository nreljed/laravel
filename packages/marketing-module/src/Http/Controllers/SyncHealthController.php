<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Services\MetricSyncHealth;
use Nreljed\MarketingModule\Services\SocialMetricSync;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\User;

/**
 * Operational view of metric collection.
 *
 * The analytics page answers "how did the audience respond"; this page answers
 * "is collection working". It shares the analytics permission because it
 * exposes the same delivery records, not credentials.
 */
class SyncHealthController extends Controller
{
    public function index(MetricSyncHealth $health): Response
    {
        $this->authorizeAnalytics();

        return Inertia::render('marketing/sync-health', [
            'health' => $health->overview(),
        ]);
    }

    /**
     * Re-queue dead letters so the next sync run picks them up again.
     */
    public function replay(Request $request, SocialMetricSync $sync): RedirectResponse
    {
        $this->authorizeAnalytics();

        $validated = $request->validate([
            'platform' => ['nullable', 'string', 'max:64'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $replayed = $sync->replayDeadLetters(
            $validated['platform'] ?? null,
            (int) ($validated['limit'] ?? 100),
        );

        return redirect()->back()->with(
            'status',
            $replayed === 1
                ? 'Re-queued 1 dead letter for retry.'
                : "Re-queued {$replayed} dead letters for retry.",
        );
    }

    /**
     * Re-queue one delivery from the dead-letter list.
     */
    public function replayTarget(SocialPostTarget $target, SocialMetricSync $sync): RedirectResponse
    {
        $this->authorizeAnalytics();

        $replayed = $sync->replayDeadLetter($target);

        return redirect()->back()->with(
            'status',
            $replayed ? 'Delivery re-queued for retry.' : 'Delivery is not a dead letter.',
        );
    }

    private function authorizeAnalytics(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->hasPermission('marketing.analytics.view'), 403);
    }
}
