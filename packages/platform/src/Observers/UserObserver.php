<?php

namespace Nreljed\Platform\Observers;

use Nreljed\Platform\Models\User;
use Nreljed\Platform\Services\DashboardService;

/**
 * Keeps dashboard aggregate stats fresh when users change.
 */
class UserObserver
{
    public function created(User $user): void
    {
        app(DashboardService::class)->clearStatsCache();
    }

    public function deleted(User $user): void
    {
        app(DashboardService::class)->clearStatsCache();
    }
}
