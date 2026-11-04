<?php

namespace Nreljed\Platform\Contracts\Capabilities;

/**
 * The feature contributes tiles and charts to the dashboard.
 */
interface HasDashboardWidgets
{
    /**
     * Dashboard tile data for this feature, keyed by feature slug by the
     * registry. The reserved keys `label`, `icon` and `total` are normalised in
     * when absent, so a partial payload degrades gracefully instead of
     * rendering a malformed tile.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(): array;

    /**
     * Analytics series merged into the dashboard charts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAnalyticsSeries(): array;
}
