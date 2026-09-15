<?php

namespace Nreljed\Platform\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Enums\AuditAction;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Services\AuditService;
use Nreljed\Platform\Services\FeatureRegistry;
use Nreljed\Platform\Services\FeatureSettingsService;

/**
 * Module-management admin page.
 *
 * Lists every registered feature (enabled or disabled) and lets an admin
 * toggle each on/off. Disabling a feature:
 *   - persists the state (settings group `core`),
 *   - fires the provider's onDisable() so it can clean up,
 *   - removes its nav/settings/permissions/dashboard data and, via the
 *     provider's own route gating, its routes.
 *
 * These endpoints are gated by the `admin` middleware (admin role only), on
 * top of the `manage-settings` permission group they are registered under.
 */
class FeatureController extends Controller
{
    public function index(FeatureRegistry $registry): Response
    {
        return Inertia::render('dashboard/admin/settings/modules', [
            'modules' => $registry->getModuleList(),
            'missingDependencies' => $registry->missingDependencies(),
        ]);
    }

    /**
     * Persist the provided feature on/off states and run disable cleanup.
     */
    public function update(Request $request, FeatureRegistry $registry, FeatureSettingsService $settings): RedirectResponse
    {
        $this->authorize('manage-settings');

        $states = $request->input('modules', []);
        if (! is_array($states)) {
            $states = [];
        }

        $bySlug = [];
        foreach ($registry->allProviders() as $provider) {
            $bySlug[$provider->slug()] = $provider;
        }

        // Only toggle providers explicitly present in the request payload;
        // anything omitted keeps its current state (defensive against partial
        // or differently-shaped payloads).
        $changes = [];

        foreach ($states as $slug => $wanted) {
            $provider = $bySlug[$slug] ?? null;
            if (! $provider) {
                continue;
            }

            $wanted = (bool) $wanted;
            $previous = $settings->setEnabled($slug, $wanted, true);

            if ($previous !== $wanted) {
                $changes[$slug] = [
                    'from' => $previous,
                    'to' => $wanted,
                ];

                // Fire cleanup only on an enable -> disable transition.
                if ($previous === true && $wanted === false) {
                    $provider->onDisable();
                }
            }
        }

        AuditService::log(AuditAction::SettingsChanged, $request->user()->id, [
            'feature_enabled' => $changes,
        ]);

        return back()->with('status', 'Module settings saved.');
    }
}
