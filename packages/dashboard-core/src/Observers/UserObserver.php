<?php

namespace Nreljed\DashboardCore\Observers;

use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Services\DashboardService;

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
