<?php

namespace Nreljed\DashboardCore\Observers;

use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Services\DashboardService;

/**
 * Keeps dashboard aggregate stats fresh when posts change.
 */
class PostObserver
{
    public function saved(Post $post): void
    {
        app(DashboardService::class)->clearStatsCache();
    }

    public function deleted(Post $post): void
    {
        app(DashboardService::class)->clearStatsCache();
    }
}
