<?php

namespace Nreljed\Platform\Contracts\Capabilities;

/**
 * The feature reports runtime health and registers scheduled work, so the
 * system-health page can show per-feature probes and the module manager can
 * unschedule tasks when the feature is disabled.
 */
interface HasHealthChecks
{
    /**
     * Health-check names to booleans, e.g. "posts_table" => true.
     *
     * @return array<string, bool>
     */
    public function getHealthChecks(): array;

    /**
     * Scheduled tasks this feature registers. Each entry has at least
     * "command" (the artisan class) and "frequency" (e.g. "daily", "hourly",
     * "every:60"), plus an optional "description".
     *
     * @return array<int, array{command: string, frequency: string, description?: string}>
     */
    public function getScheduledTasks(): array;
}
