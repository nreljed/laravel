<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Nreljed\Platform\Services\FeatureRegistry;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

foreach (app(FeatureRegistry::class)->getAllScheduledTasks() as $task) {
    if ($task['command'] === 'schedule:publish') {
        continue;
    }

    $schedule = Schedule::command($task['command']);

    $frequency = $task['frequency'];

    if (str_starts_with($frequency, 'every:')) {
        $minutes = (int) substr($frequency, 6);
        $schedule->cron($minutes === 1 ? '* * * * *' : "*/{$minutes} * * * *");
    } else {
        match ($frequency) {
            'everyMinute' => $schedule->everyMinute(),
            'everyFiveMinutes' => $schedule->everyFiveMinutes(),
            'hourly' => $schedule->hourly(),
            'daily' => $schedule->daily(),
            'weekly' => $schedule->weekly(),
            'monthly' => $schedule->monthly(),
            default => $schedule->hourly(),
        };
    }

    $schedule->withoutOverlapping()->name('feature:'.$task['provider'].':'.$task['command']);
}
