<?php

namespace Nreljed\DashboardCore\Ai;

use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }

    /**
     * Get navigation items for the AI feature (future: sidebar integration).
     *
     * @return list<array{label: string, url: string, icon: string}>
     */
    public function getNavItems(): array
    {
        return [];
    }

    /**
     * Get shared Inertia props for the AI feature.
     */
    public function getSharedData(): array
    {
        return [];
    }

    /**
     * Get permission slugs defined by this feature.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return [];
    }
}
