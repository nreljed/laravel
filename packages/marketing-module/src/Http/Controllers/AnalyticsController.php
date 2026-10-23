<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Nreljed\MarketingModule\Services\MarketingAnalytics;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\User;

/**
 * The reporting page.
 *
 * It separates what the engine did (deliveries, campaigns, workflows) from
 * what the audience did, because the module cannot observe the latter.
 */
class AnalyticsController extends Controller
{
    public function index(MarketingAnalytics $analytics): Response
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->hasPermission('marketing.analytics.view'), 403);

        return Inertia::render('marketing/analytics', [
            'analytics' => $analytics->overview(),
        ]);
    }
}
